<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\BusinessType;
use App\Models\FormTemplate;
use App\Models\Project;
use App\Models\ProjectForm;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para gestión de proyectos
 *
 * Maneja operaciones CRUD de proyectos:
 * - Crear proyectos con templates automáticos
 * - Listar proyectos del usuario
 * - Actualizar información del proyecto
 * - Eliminar proyectos (solo owner)
 * - Gestión de configuraciones
 * - Estadísticas y métricas del proyecto
 */
class ProjectController extends Controller
{
    /**
     * Lista proyectos del usuario autenticado
     *
     * Muestra proyectos donde el usuario es:
     * - Propietario (owner_id)
     * - Miembro activo del equipo
     *
     * Incluye contadores de recursos y información del equipo
     *
     * @param Request $request Usuario autenticado
     * @return \Illuminate\Http\JsonResponse Lista de proyectos con estadísticas
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Consulta para obtener proyectos del usuario (propios + donde es miembro)
        $projects = Project::where(function ($query) use ($user) {
            $query->where('owner_id', $user->id) // Proyectos propios
                ->orWhereHas('users', function ($subQuery) use ($user) {
                    $subQuery->where('user_id', $user->id)
                        ->where('status', 'active'); // Solo membresías activas
                });
        })
            // Eager loading para optimizar consultas
            ->with([
                'businessType',
                'owner:id,name,email,avatar',
                'users' => function ($query) {
                    $query->where('status', 'active')
                        ->with('role:id,name,display_name')
                        ->select('users.id', 'users.name', 'users.email', 'users.avatar');
                }
            ])
            // Contadores para dashboard y estadísticas
            ->withCount([
                'contacts',
                'appointments',
                'users as active_members_count' => function ($query) {
                    $query->where('status', 'active');
                }
            ])
            ->active() // Solo proyectos activos
            ->orderBy('created_at', 'desc') // Más recientes primero
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Proyectos obtenidos exitosamente',
            'data' => ProjectResource::collection($projects),
            'meta' => [
                'total_projects' => $projects->count(),
                'owned_projects' => $projects->where('owner_id', $user->id)->count(),
                'member_projects' => $projects->where('owner_id', '!=', $user->id)->count()
            ]
        ]);
    }

    /**
     * Crea un nuevo proyecto
     *
     * Proceso completo de creación:
     * 1. Valida datos del proyecto
     * 2. Crea el proyecto en la base de datos
     * 3. Aplica template automático según tipo de negocio
     * 4. Asigna configuraciones por defecto
     * 5. Retorna proyecto con próximos pasos
     *
     * @param StoreProjectRequest $request Datos validados del proyecto
     * @return \Illuminate\Http\JsonResponse Proyecto creado con guía de configuración
     */
    public function store(StoreProjectRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();

        // Usar transacción para garantizar consistencia de datos
        DB::beginTransaction();

        try {
            // Crear el proyecto con datos validados
            $project = Project::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'owner_id' => $user->id, // Usuario actual como propietario
                'business_type_id' => $validated['business_type_id'],
                'status' => 'active',
                'settings' => $this->getDefaultSettings($validated['business_type_id']) // Configuraciones específicas del tipo
            ]);

            // Aplicar template automático si el tipo de negocio lo tiene configurado
            $this->applyBusinessTypeTemplate($project, $validated['business_type_id']);

            // Cargar relaciones para la respuesta
            $project->load([
                'businessType',
                'owner:id,name,email,avatar',
                'projectForms.template'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proyecto creado exitosamente',
                'data' => new ProjectResource($project),
                // Guía para completar la configuración del proyecto
                'next_steps' => [
                    'configure_services' => 'Agregar servicios que ofreces',
                    'customize_forms' => 'Personalizar formularios de atención',
                    'invite_team' => 'Invitar miembros del equipo',
                    'setup_schedule' => 'Configurar horarios de trabajo'
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir cambios en caso de error

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el proyecto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Muestra un proyecto específico con todos sus datos
     *
     * Retorna información completa del proyecto incluyendo:
     * - Datos básicos y configuraciones
     * - Miembros del equipo con roles
     * - Formularios y servicios activos
     * - Estadísticas de uso
     * - Permisos del usuario actual
     *
     * @param Request $request Usuario autenticado
     * @param Project $project Proyecto a mostrar
     * @return \Illuminate\Http\JsonResponse Datos completos del proyecto
     */
    public function show(Request $request, Project $project)
    {
        $user = $request->user();

        // Verificar que el usuario tenga acceso al proyecto
        if (!$user->hasAccessToProject($project)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a este proyecto'
            ], 403);
        }

        // Cargar todas las relaciones necesarias para el dashboard del proyecto
        $project->load([
            'businessType',
            'owner:id,name,email,avatar',
            'users' => function ($query) {
                $query->where('status', 'active')
                    ->with('role:id,name,display_name')
                    ->select('users.id', 'users.name', 'users.email', 'users.avatar');
            },
            'projectForms' => function ($query) {
                $query->where('is_active', true)->with('template');
            },
            'services' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            },
            'tags',
            'workingHours' => function ($query) {
                $query->where('is_active', true)->orderBy('day_of_week');
            }
        ]);

        // Cargar contadores para métricas del dashboard
        $project->loadCount([
            'contacts',
            'appointments',
            'appointments as upcoming_appointments_count' => function ($query) {
                $query->where('start_time', '>=', now())
                    ->whereIn('status', ['scheduled', 'confirmed']);
            },
            'appointments as completed_appointments_count' => function ($query) {
                $query->where('status', 'completed');
            }
        ]);

        // Obtener rol y permisos del usuario en este proyecto
        $userRole = $user->getRoleInProject($project);

        return response()->json([
            'success' => true,
            'message' => 'Proyecto obtenido exitosamente',
            'data' => new ProjectResource($project),
            // Contexto del usuario para mostrar/ocultar funcionalidades en el frontend
            'user_context' => [
                'role' => $userRole ? [
                    'id' => $userRole->id,
                    'name' => $userRole->name,
                    'display_name' => $userRole->display_name,
                    'permissions' => $userRole->permissions
                ] : null,
                'is_owner' => $project->isOwner($user),
                'can_edit' => $user->hasPermissionInProject($project, 'project', 'edit'),
                'can_delete' => $user->hasPermissionInProject($project, 'project', 'delete'),
                'can_manage_users' => $user->hasPermissionInProject($project, 'users', 'manage_roles')
            ]
        ]);
    }

    /**
     * Actualiza un proyecto existente
     *
     * Permite modificar información básica del proyecto como:
     * - Nombre y descripción
     * - Configuraciones generales
     *
     * Requiere permisos de edición en el proyecto
     *
     * @param UpdateProjectRequest $request Datos validados de actualización
     * @param Project $project Proyecto a actualizar
     * @return \Illuminate\Http\JsonResponse Proyecto actualizado
     */
    public function update(UpdateProjectRequest $request, Project $project)
    {
        $user = $request->user();

        // Verificar que el usuario tenga permisos de edición
        if (!$user->hasPermissionInProject($project, 'project', 'edit')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para editar este proyecto'
            ], 403);
        }

        $validated = $request->validated();

        // Actualizar solo los campos permitidos
        $project->update($validated);

        // Recargar con relaciones actualizadas
        $project->load([
            'businessType',
            'owner:id,name,email,avatar'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Proyecto actualizado exitosamente',
            'data' => new ProjectResource($project)
        ]);
    }

    /**
     * Elimina un proyecto permanentemente
     *
     * Restricciones de seguridad:
     * - Solo el propietario puede eliminar el proyecto
     * - No se puede eliminar si tiene citas futuras programadas
     * - Eliminación en cascada de todos los datos relacionados
     *
     * @param Request $request Usuario autenticado
     * @param Project $project Proyecto a eliminar
     * @return \Illuminate\Http\JsonResponse Confirmación de eliminación
     */
    public function destroy(Request $request, Project $project)
    {
        $user = $request->user();

        // Solo el propietario puede eliminar el proyecto (protección crítica)
        if (!$project->isOwner($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Solo el propietario puede eliminar el proyecto'
            ], 403);
        }

        // Verificar que no tenga citas futuras (evitar pérdida de datos importantes)
        $upcomingAppointments = $project->appointments()
            ->where('start_time', '>=', now())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->count();

        if ($upcomingAppointments > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar el proyecto. Tienes {$upcomingAppointments} citas programadas.",
                'suggestion' => 'Cancela o completa las citas pendientes antes de eliminar el proyecto.'
            ], 422);
        }

        $projectName = $project->name;
        $project->delete(); // Eliminación en cascada por configuración del modelo

        return response()->json([
            'success' => true,
            'message' => "Proyecto '{$projectName}' eliminado exitosamente"
        ]);
    }

    /**
     * Obtiene estadísticas detalladas de un proyecto
     *
     * Métricas incluidas:
     * - Contactos (total, nuevos este mes)
     * - Citas (total, completadas, próximas, hoy, esta semana)
     * - Equipo (miembros activos, invitaciones pendientes)
     * - Servicios (total, más popular)
     * - Formularios (total, completados)
     *
     * @param Request $request Usuario autenticado
     * @param Project $project Proyecto
     * @return \Illuminate\Http\JsonResponse Estadísticas completas con períodos
     */
    public function stats(Request $request, Project $project)
    {
        $user = $request->user();

        // Verificar acceso al proyecto
        if (!$user->hasAccessToProject($project)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a este proyecto'
            ], 403);
        }

        // Calcular estadísticas categorizadas para dashboard
        $stats = [
            'contacts' => [
                'total' => $project->contacts()->count(),
                'this_month' => $project->contacts()
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count()
            ],
            'appointments' => [
                'total' => $project->appointments()->count(),
                'completed' => $project->appointments()->where('status', 'completed')->count(),
                'upcoming' => $project->appointments()
                    ->where('start_time', '>=', now())
                    ->whereIn('status', ['scheduled', 'confirmed'])
                    ->count(),
                'today' => $project->appointments()
                    ->whereDate('start_time', today())
                    ->count(),
                'this_week' => $project->appointments()
                    ->whereBetween('start_time', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ])
                    ->count()
            ],
            'team' => [
                'total_members' => $project->users()->where('status', 'active')->count() + 1, // +1 para el owner
                'pending_invitations' => $project->invitations()->where('status', 'pending')->count()
            ],
            'services' => [
                'total' => $project->services()->where('is_active', true)->count(),
                'most_popular' => $this->getMostPopularService($project) // Servicio con más citas
            ],
            'forms' => [
                'total' => $project->projectForms()->where('is_active', true)->count(),
                'completed_this_month' => $project->appointments()
                    ->whereHas('appointmentForm')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count()
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Estadísticas obtenidas exitosamente',
            'data' => $stats,
            // Información de períodos para contexto temporal
            'period' => [
                'current_month' => now()->format('F Y'),
                'current_week' => now()->startOfWeek()->format('M d') . ' - ' . now()->endOfWeek()->format('M d'),
                'today' => now()->format('Y-m-d')
            ]
        ]);
    }

    /**
     * Actualiza configuraciones específicas del proyecto
     *
     * Configuraciones disponibles:
     * - Zona horaria y duración de citas por defecto
     * - Reservas online y confirmaciones
     * - Recordatorios automáticos
     * - Horarios de trabajo
     * - Preferencias de notificaciones
     *
     * @param Request $request Configuraciones a actualizar
     * @param Project $project Proyecto a configurar
     * @return \Illuminate\Http\JsonResponse Configuraciones actualizadas
     */
    public function updateSettings(Request $request, Project $project)
    {
        $user = $request->user();

        // Verificar permisos de configuración (solo admin/owner)
        if (!$user->hasPermissionInProject($project, 'project', 'settings')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para configurar este proyecto'
            ], 403);
        }

        // Validar configuraciones permitidas
        $request->validate([
            'timezone' => 'sometimes|string|timezone',
            'default_appointment_duration' => 'sometimes|integer|min:15|max:480', // 15 min a 8 horas
            'allow_online_booking' => 'sometimes|boolean',
            'require_appointment_confirmation' => 'sometimes|boolean',
            'send_reminders' => 'sometimes|boolean',
            'reminder_hours_before' => 'sometimes|integer|min:1|max:168', // 1 hora a 1 semana
            'business_hours' => 'sometimes|array',
            'notification_preferences' => 'sometimes|array'
        ]);

        // Combinar configuraciones existentes con las nuevas (merge)
        $currentSettings = $project->settings ?? [];
        $newSettings = array_merge($currentSettings, $request->all());

        $project->update(['settings' => $newSettings]);

        return response()->json([
            'success' => true,
            'message' => 'Configuraciones actualizadas exitosamente',
            'data' => [
                'settings' => $newSettings
            ]
        ]);
    }

    /**
     * Aplica template de formulario automáticamente según tipo de negocio
     *
     * Si el tipo de negocio tiene un template por defecto configurado,
     * lo crea automáticamente como formulario del proyecto para
     * acelerar la configuración inicial.
     *
     * @param Project $project Proyecto recién creado
     * @param int $businessTypeId ID del tipo de negocio
     * @return void
     */
    private function applyBusinessTypeTemplate(Project $project, int $businessTypeId): void
    {
        $businessType = BusinessType::find($businessTypeId);
        $defaultTemplate = $businessType?->getDefaultTemplate();

        if ($defaultTemplate) {
            // Crear formulario basado en el template del tipo de negocio
            ProjectForm::create([
                'project_id' => $project->id,
                'name' => $defaultTemplate->name,
                'description' => $defaultTemplate->description,
                'fields' => $defaultTemplate->fields, // Estructura JSON de campos
                'is_active' => true,
                'created_from_template_id' => $defaultTemplate->id,
                'created_by' => $project->owner_id
            ]);
        }
    }

    /**
     * Obtiene configuraciones por defecto específicas según tipo de negocio
     *
     * Cada tipo de negocio tiene configuraciones optimizadas:
     * - Barbería: citas de 45 min, recordatorios 2h antes
     * - Consultorio: citas de 30 min, confirmación requerida
     * - Salón: citas de 90 min, recordatorios 4h antes
     * - Default: configuraciones generales
     *
     * @param int $businessTypeId ID del tipo de negocio
     * @return array Configuraciones optimizadas para el tipo
     */
    private function getDefaultSettings(int $businessTypeId): array
    {
        $businessType = BusinessType::find($businessTypeId);

        // Configuraciones base aplicables a todos los tipos
        $baseSettings = [
            'timezone' => 'America/Mexico_City',
            'default_appointment_duration' => 60, // 1 hora por defecto
            'allow_online_booking' => true,
            'require_appointment_confirmation' => false,
            'send_reminders' => true,
            'reminder_hours_before' => 24, // 1 día antes
            'notification_preferences' => [
                'email_notifications' => true,
                'appointment_created' => true,
                'appointment_cancelled' => true,
                'daily_summary' => false
            ]
        ];

        // Configuraciones específicas optimizadas por tipo de negocio
        return match ($businessType?->name) {
            'Barbería' => array_merge($baseSettings, [
                'default_appointment_duration' => 45, // Citas más cortas
                'reminder_hours_before' => 2 // Recordatorios el mismo día
            ]),
            'Consultorio Médico' => array_merge($baseSettings, [
                'default_appointment_duration' => 30, // Consultas rápidas
                'require_appointment_confirmation' => true, // Confirmación obligatoria
                'reminder_hours_before' => 24 // Recordatorio con anticipación
            ]),
            'Salón de Belleza' => array_merge($baseSettings, [
                'default_appointment_duration' => 90, // Servicios más largos
                'reminder_hours_before' => 4 // Recordatorio algunas horas antes
            ]),
            default => $baseSettings // Configuraciones generales
        };
    }

    /**
     * Identifica el servicio más popular del proyecto
     *
     * Analiza todos los servicios activos y determina cuál
     * tiene más citas programadas. Útil para métricas
     * y recomendaciones de negocio.
     *
     * @param Project $project Proyecto a analizar
     * @return array|null Datos del servicio más demandado
     */
    private function getMostPopularService(Project $project): ?array
    {
        $service = $project->services()
            ->withCount('appointments') // Contar citas por servicio
            ->orderByDesc('appointments_count') // Más popular primero
            ->first();

        if (!$service) {
            return null; // No hay servicios configurados
        }

        return [
            'id' => $service->id,
            'name' => $service->name,
            'appointments_count' => $service->appointments_count,
            'price' => $service->price
        ];
    }
}
