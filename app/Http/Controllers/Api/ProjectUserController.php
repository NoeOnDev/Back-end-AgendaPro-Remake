<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteUserRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Resources\ProjectUserResource;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\ProjectUser;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Controlador para gestión de miembros del equipo
 *
 * Maneja operaciones relacionadas con miembros de proyectos:
 * - Listar miembros del equipo
 * - Invitar nuevos usuarios
 * - Gestionar roles y permisos
 * - Remover miembros del proyecto
 * - Aceptar/rechazar invitaciones
 * - Administrar el ciclo completo de membresías
 */
class ProjectUserController extends Controller
{
    /**
     * Lista todos los miembros de un proyecto
     *
     * Muestra información completa del equipo incluyendo:
     * - Propietario del proyecto (owner)
     * - Miembros activos con sus roles
     * - Invitaciones pendientes
     * - Permisos del usuario actual para cada acción
     *
     * @param Request $request Usuario autenticado
     * @param Project $project Proyecto
     * @return \Illuminate\Http\JsonResponse Lista completa del equipo
     */
    public function index(Request $request, Project $project)
    {
        $user = $request->user();

        // Verificar acceso al proyecto
        if (!$user->hasAccessToProject($project)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a este proyecto'
            ], 403);
        }

        // Verificar permisos para ver usuarios
        if (!$user->hasPermissionInProject($project, 'users', 'view')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para ver los miembros del equipo'
            ], 403);
        }

        // Obtener owner del proyecto (siempre presente)
        $owner = $project->owner;
        $ownerRole = Role::where('name', 'owner')->first();

        // Obtener miembros activos con eager loading para optimizar consultas
        $activeMembers = $project->projectUsers()
            ->where('status', 'active')
            ->with(['user:id,name,email,avatar', 'role:id,name,display_name,permissions', 'invitedBy:id,name'])
            ->get();

        // Obtener invitaciones pendientes no expiradas
        $pendingInvitations = $project->invitations()
            ->where('status', 'pending')
            ->where('expires_at', '>', now()) // Solo invitaciones vigentes
            ->with(['role:id,name,display_name', 'invitedBy:id,name,email'])
            ->get();

        // Preparar respuesta con owner incluido (no es ProjectUser, es directo)
        $members = collect([
            [
                'id' => null, // No tiene ProjectUser ID porque es owner
                'user' => [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'avatar' => $owner->avatar ? url('storage/avatars/' . $owner->avatar) : null
                ],
                'role' => [
                    'id' => $ownerRole->id,
                    'name' => $ownerRole->name,
                    'display_name' => $ownerRole->display_name,
                    'permissions' => $ownerRole->permissions
                ],
                'status' => 'owner',
                'is_owner' => true,
                'joined_at' => $project->created_at, // El owner se une cuando crea el proyecto
                'invited_by' => null, // El owner no fue invitado
                'can_remove' => false // Nunca se puede remover al owner
            ]
        ]);

        // Agregar miembros activos del equipo
        foreach ($activeMembers as $member) {
            $members->push([
                'id' => $member->id,
                'user' => [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'avatar' => $member->user->avatar ? url('storage/avatars/' . $member->user->avatar) : null
                ],
                'role' => [
                    'id' => $member->role->id,
                    'name' => $member->role->name,
                    'display_name' => $member->role->display_name,
                    'permissions' => $member->role->permissions
                ],
                'status' => $member->status,
                'is_owner' => false,
                'joined_at' => $member->joined_at,
                'invited_by' => $member->invitedBy ? [
                    'id' => $member->invitedBy->id,
                    'name' => $member->invitedBy->name
                ] : null,
                // Determinar si el usuario actual puede remover este miembro
                'can_remove' => $user->hasPermissionInProject($project, 'users', 'remove')
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Miembros del equipo obtenidos exitosamente',
            'data' => [
                'members' => $members,
                'pending_invitations' => $pendingInvitations->map(function ($invitation) use ($user, $project) {
                    return [
                        'id' => $invitation->id,
                        'email' => $invitation->email,
                        'role' => [
                            'id' => $invitation->role->id,
                            'name' => $invitation->role->name,
                            'display_name' => $invitation->role->display_name
                        ],
                        'status' => $invitation->status,
                        'invited_by' => [
                            'id' => $invitation->invitedBy->id,
                            'name' => $invitation->invitedBy->name,
                            'email' => $invitation->invitedBy->email
                        ],
                        'expires_at' => $invitation->expires_at,
                        'created_at' => $invitation->created_at,
                        // Determinar si puede cancelar esta invitación
                        'can_cancel' => $user->hasPermissionInProject($project, 'users', 'invite')
                    ];
                }),
                // Resumen estadístico del equipo
                'summary' => [
                    'total_members' => $members->count(),
                    'active_members' => $activeMembers->count() + 1, // +1 por owner
                    'pending_invitations' => $pendingInvitations->count(),
                    // Roles disponibles para invitaciones (excluyendo owner)
                    'available_roles' => Role::system()
                        ->where('name', '!=', 'owner')
                        ->select('id', 'name', 'display_name', 'description')
                        ->get()
                ]
            ]
        ]);
    }

    /**
     * Invita un nuevo usuario al proyecto
     *
     * Proceso de invitación:
     * 1. Valida permisos y datos de entrada
     * 2. Verifica que no sea miembro existente
     * 3. Verifica que no tenga invitación pendiente
     * 4. Crea invitación con token único
     * 5. Envía notificación por email
     *
     * @param InviteUserRequest $request Datos validados de invitación
     * @param Project $project Proyecto
     * @return \Illuminate\Http\JsonResponse Invitación creada
     */
    public function invite(InviteUserRequest $request, Project $project)
    {
        $user = $request->user();

        // Verificar permisos para invitar usuarios
        if (!$user->hasPermissionInProject($project, 'users', 'invite')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para invitar usuarios'
            ], 403);
        }

        $validated = $request->validated();

        // Usar transacción para garantizar consistencia
        DB::beginTransaction();

        try {
            // Verificar si el usuario ya es miembro del proyecto
            $existingUser = User::where('email', $validated['email'])->first();
            if ($existingUser && $project->hasUser($existingUser)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este usuario ya es miembro del proyecto'
                ], 422);
            }

            // Verificar si ya existe una invitación pendiente vigente
            $existingInvitation = $project->invitations()
                ->where('email', $validated['email'])
                ->where('status', 'pending')
                ->where('expires_at', '>', now()) // Solo considerar invitaciones no expiradas
                ->first();

            if ($existingInvitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una invitación pendiente para este email',
                    'data' => [
                        'existing_invitation' => [
                            'expires_at' => $existingInvitation->expires_at,
                            'role' => $existingInvitation->role->display_name
                        ]
                    ]
                ], 422);
            }

            // Crear nueva invitación con token único
            $invitation = ProjectInvitation::create([
                'project_id' => $project->id,
                'email' => $validated['email'],
                'role_id' => $validated['role_id'],
                'token' => Str::random(64), // Token único para seguridad
                'status' => 'pending',
                'invited_by' => $user->id,
                'expires_at' => now()->addDays(7) // Invitación válida por 7 días
            ]);

            // Cargar relaciones para la respuesta
            $invitation->load(['role:id,name,display_name', 'invitedBy:id,name,email']);

            // Enviar notificación por email al usuario invitado
            $invitation->notify(new ProjectInvitationNotification($project, $user, $invitation->token));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Invitación enviada exitosamente',
                'data' => [
                    'invitation' => [
                        'id' => $invitation->id,
                        'email' => $invitation->email,
                        'role' => [
                            'id' => $invitation->role->id,
                            'name' => $invitation->role->name,
                            'display_name' => $invitation->role->display_name
                        ],
                        'status' => $invitation->status,
                        'invited_by' => [
                            'id' => $invitation->invitedBy->id,
                            'name' => $invitation->invitedBy->name,
                            'email' => $invitation->invitedBy->email
                        ],
                        'expires_at' => $invitation->expires_at,
                        'created_at' => $invitation->created_at
                    ]
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir cambios en caso de error

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la invitación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Acepta una invitación de proyecto
     *
     * Proceso de aceptación:
     * 1. Valida token y estado de invitación
     * 2. Verifica que el email coincida con el usuario
     * 3. Verifica que no sea ya miembro
     * 4. Crea ProjectUser activo
     * 5. Marca invitación como aceptada
     *
     * @param Request $request Usuario autenticado
     * @param string $token Token único de invitación
     * @return \Illuminate\Http\JsonResponse Confirmación de unión al proyecto
     */
    public function acceptInvitation(Request $request, string $token)
    {
        $user = $request->user();

        // Buscar invitación válida y no expirada
        $invitation = ProjectInvitation::where('token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now()) // Verificar que no haya expirado
            ->with(['project', 'role'])
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitación inválida o expirada'
            ], 404);
        }

        // Verificar que el email de la invitación coincida con el usuario autenticado
        if ($invitation->email !== $user->email) {
            return response()->json([
                'success' => false,
                'message' => 'Esta invitación no es para tu cuenta',
                'data' => [
                    'invitation_email' => $invitation->email,
                    'your_email' => $user->email
                ]
            ], 403);
        }

        // Verificar que no sea ya miembro del proyecto
        if ($invitation->project->hasUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Ya eres miembro de este proyecto'
            ], 422);
        }

        // Usar transacción para consistencia
        DB::beginTransaction();

        try {
            // Crear ProjectUser activo
            $projectUser = ProjectUser::create([
                'project_id' => $invitation->project_id,
                'user_id' => $user->id,
                'role_id' => $invitation->role_id,
                'status' => 'active', // Inmediatamente activo al aceptar
                'invited_by' => $invitation->invited_by,
                'invited_at' => $invitation->created_at, // Fecha original de invitación
                'joined_at' => now() // Fecha de aceptación
            ]);

            // Marcar invitación como aceptada (para auditoría)
            $invitation->update(['status' => 'accepted']);

            // Cargar relaciones para respuesta completa
            $projectUser->load(['project:id,name,description', 'role:id,name,display_name,permissions']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Te has unido al proyecto exitosamente',
                'data' => [
                    'project' => [
                        'id' => $projectUser->project->id,
                        'name' => $projectUser->project->name,
                        'description' => $projectUser->project->description
                    ],
                    'role' => [
                        'id' => $projectUser->role->id,
                        'name' => $projectUser->role->name,
                        'display_name' => $projectUser->role->display_name,
                        'permissions' => $projectUser->role->permissions
                    ],
                    'joined_at' => $projectUser->joined_at
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir en caso de error

            return response()->json([
                'success' => false,
                'message' => 'Error al aceptar la invitación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechaza una invitación de proyecto
     *
     * Permite al usuario rechazar explícitamente una invitación,
     * marcándola como rechazada para auditoría y notificación
     * al usuario que envió la invitación.
     *
     * @param Request $request Usuario autenticado
     * @param string $token Token único de invitación
     * @return \Illuminate\Http\JsonResponse Confirmación de rechazo
     */
    public function rejectInvitation(Request $request, string $token)
    {
        $user = $request->user();

        // Buscar invitación válida
        $invitation = ProjectInvitation::where('token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitación inválida o expirada'
            ], 404);
        }

        // Verificar que el email coincida
        if ($invitation->email !== $user->email) {
            return response()->json([
                'success' => false,
                'message' => 'Esta invitación no es para tu cuenta'
            ], 403);
        }

        // Marcar como rechazada (para auditoría y notificación)
        $invitation->update(['status' => 'rejected']);

        return response()->json([
            'success' => true,
            'message' => 'Invitación rechazada'
        ]);
    }

    /**
     * Actualiza el rol de un miembro del proyecto
     *
     * Permite cambiar el rol y permisos de un miembro existente.
     * Incluye validaciones de seguridad para evitar escalación
     * de privilegios no autorizada.
     *
     * @param UpdateUserRoleRequest $request Datos validados de actualización
     * @param Project $project Proyecto
     * @param ProjectUser $projectUser Miembro del proyecto
     * @return \Illuminate\Http\JsonResponse Rol actualizado
     */
    public function updateRole(UpdateUserRoleRequest $request, Project $project, ProjectUser $projectUser)
    {
        $user = $request->user();

        // Verificar que el ProjectUser pertenece al proyecto especificado
        if ($projectUser->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado en este proyecto'
            ], 404);
        }

        // Verificar permisos para gestionar roles
        if (!$user->hasPermissionInProject($project, 'users', 'manage_roles')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para gestionar roles'
            ], 403);
        }

        // Restricción crítica: No se puede cambiar el rol del owner
        if ($project->isOwner($projectUser->user)) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cambiar el rol del propietario'
            ], 422);
        }

        $validated = $request->validated();

        // Verificar que no intenta asignar rol de owner (seguridad)
        $newRole = Role::findOrFail($validated['role_id']);
        if ($newRole->name === 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede asignar el rol de propietario',
                'suggestion' => 'Use la funcionalidad de transferencia de propiedad si es necesario'
            ], 422);
        }

        // Guardar rol anterior para auditoría
        $oldRole = $projectUser->role;

        // Actualizar rol del miembro
        $projectUser->update(['role_id' => $validated['role_id']]);

        // Cargar nueva información con relaciones
        $projectUser->load(['role:id,name,display_name,permissions', 'user:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Rol actualizado exitosamente',
            'data' => [
                'user' => [
                    'id' => $projectUser->user->id,
                    'name' => $projectUser->user->name,
                    'email' => $projectUser->user->email
                ],
                'old_role' => [
                    'id' => $oldRole->id,
                    'name' => $oldRole->name,
                    'display_name' => $oldRole->display_name
                ],
                'new_role' => [
                    'id' => $projectUser->role->id,
                    'name' => $projectUser->role->name,
                    'display_name' => $projectUser->role->display_name,
                    'permissions' => $projectUser->role->permissions
                ]
            ]
        ]);
    }

    /**
     * Remueve un miembro del proyecto
     *
     * Elimina permanentemente a un usuario del proyecto.
     * Incluye múltiples validaciones de seguridad y restricciones
     * para evitar eliminaciones no autorizadas.
     *
     * @param Request $request Usuario autenticado
     * @param Project $project Proyecto
     * @param ProjectUser $projectUser Miembro a remover
     * @return \Illuminate\Http\JsonResponse Confirmación de eliminación
     */
    public function removeMember(Request $request, Project $project, ProjectUser $projectUser)
    {
        $user = $request->user();

        // Verificar que el ProjectUser pertenece al proyecto
        if ($projectUser->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado en este proyecto'
            ], 404);
        }

        // Verificar permisos para remover usuarios
        if (!$user->hasPermissionInProject($project, 'users', 'remove')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para remover miembros'
            ], 403);
        }

        // Restricción crítica: No se puede remover al owner
        if ($project->isOwner($projectUser->user)) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede remover al propietario del proyecto',
                'suggestion' => 'Para remover al propietario, primero transfiere la propiedad a otro usuario'
            ], 422);
        }

        // Restricción: No se puede remover a sí mismo (debe usar leave)
        if ($projectUser->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes removerte a ti mismo. Usa la opción "Abandonar proyecto"'
            ], 422);
        }

        // Guardar información del usuario antes de eliminar
        $removedUser = $projectUser->user;

        // Eliminar el ProjectUser (esto activará eventos de auditoría si están configurados)
        $projectUser->delete();

        return response()->json([
            'success' => true,
            'message' => "{$removedUser->name} ha sido removido del proyecto"
        ]);
    }

    /**
     * Abandona un proyecto voluntariamente
     *
     * Permite a un miembro del equipo salir voluntariamente del proyecto.
     * Esta acción es irreversible y requiere una nueva invitación
     * para volver al proyecto.
     *
     * @param Request $request Usuario autenticado
     * @param Project $project Proyecto
     * @return \Illuminate\Http\JsonResponse Confirmación de salida
     */
    public function leaveProject(Request $request, Project $project)
    {
        $user = $request->user();

        // Restricción crítica: El propietario no puede abandonar el proyecto
        if ($project->isOwner($user)) {
            return response()->json([
                'success' => false,
                'message' => 'El propietario no puede abandonar el proyecto. Debe transferir la propiedad o eliminar el proyecto.',
                'suggestions' => [
                    'transfer_ownership' => 'Transferir propiedad a otro miembro del equipo',
                    'delete_project' => 'Eliminar completamente el proyecto'
                ]
            ], 422);
        }

        // Buscar ProjectUser activo del usuario
        $projectUser = $project->projectUsers()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$projectUser) {
            return response()->json([
                'success' => false,
                'message' => 'No eres miembro de este proyecto'
            ], 422);
        }

        // Eliminar el ProjectUser (salida voluntaria)
        $projectUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'Has abandonado el proyecto exitosamente'
        ]);
    }

    /**
     * Cancela una invitación pendiente
     *
     * Permite cancelar invitaciones que aún no han sido aceptadas
     * o rechazadas. Útil cuando se envió por error o cuando
     * ya no se desea que el usuario se una al proyecto.
     *
     * @param Request $request Usuario autenticado
     * @param Project $project Proyecto
     * @param ProjectInvitation $invitation Invitación a cancelar
     * @return \Illuminate\Http\JsonResponse Confirmación de cancelación
     */
    public function cancelInvitation(Request $request, Project $project, ProjectInvitation $invitation)
    {
        $user = $request->user();

        // Verificar que la invitación pertenece al proyecto
        if ($invitation->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invitación no encontrada en este proyecto'
            ], 404);
        }

        // Verificar permisos para gestionar invitaciones
        if (!$user->hasPermissionInProject($project, 'users', 'invite')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para cancelar invitaciones'
            ], 403);
        }

        // Verificar que la invitación esté pendiente (no se pueden cancelar las ya procesadas)
        if ($invitation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden cancelar invitaciones pendientes',
                'data' => [
                    'current_status' => $invitation->status,
                    'invitation_email' => $invitation->email
                ]
            ], 422);
        }

        // Cancelar invitación (marcar como cancelada para auditoría)
        $invitation->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Invitación cancelada exitosamente'
        ]);
    }

    /**
     * Obtiene roles disponibles para asignación en el proyecto
     *
     * Lista todos los roles del sistema que pueden ser asignados
     * a miembros del equipo, excluyendo el rol de 'owner' que
     * es único y solo se asigna al propietario del proyecto.
     *
     * @param Request $request Usuario autenticado
     * @param Project $project Proyecto
     * @return \Illuminate\Http\JsonResponse Lista de roles disponibles
     */
    public function getAvailableRoles(Request $request, Project $project)
    {
        $user = $request->user();

        // Verificar acceso al proyecto
        if (!$user->hasAccessToProject($project)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a este proyecto'
            ], 403);
        }

        // Obtener roles del sistema excepto owner (que es único)
        $roles = Role::system()
            ->where('name', '!=', 'owner') // Excluir owner para evitar conflictos
            ->select('id', 'name', 'display_name', 'description', 'permissions')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Roles disponibles obtenidos exitosamente',
            'data' => $roles,
            'meta' => [
                'total_roles' => $roles->count(),
                'note' => 'El rol de propietario no está disponible para asignación manual'
            ]
        ]);
    }
}
