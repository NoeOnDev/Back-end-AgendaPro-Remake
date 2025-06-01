<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessTypeResource;
use App\Models\BusinessType;
use Illuminate\Http\Request;

/**
 * Controlador de tipos de negocio para la API
 *
 * Maneja todas las operaciones relacionadas con los tipos de negocio:
 * - Listado de tipos de negocio disponibles
 * - Consulta de información específica de un tipo
 * - Obtención de templates por defecto
 * - Estadísticas de uso y popularidad
 * - Búsqueda y filtrado de tipos
 */
class BusinessTypeController extends Controller
{
    /**
     * Obtiene todos los tipos de negocio activos disponibles
     *
     * Endpoint público usado principalmente durante el registro
     * para mostrar las opciones de tipo de negocio al usuario.
     * Incluye templates por defecto para preview.
     *
     * @return \Illuminate\Http\JsonResponse Lista de tipos de negocio activos
     */
    public function index()
    {
        // Obtener solo tipos de negocio activos con eager loading de templates
        $businessTypes = BusinessType::active()
            ->with(['formTemplates' => function ($query) {
                $query->where('is_default', true); // Solo cargar template predeterminado
            }])
            ->orderBy('name') // Ordenar alfabéticamente para mejor UX
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Tipos de negocio obtenidos exitosamente',
            'data' => BusinessTypeResource::collection($businessTypes),
            'meta' => [
                'total' => $businessTypes->count(),
                // Contar cuántos tipos tienen templates configurados
                'with_templates' => $businessTypes->filter(function ($type) {
                    return $type->formTemplates->isNotEmpty();
                })->count()
            ]
        ]);
    }

    /**
     * Obtiene información detallada de un tipo de negocio específico
     *
     * Retorna los datos completos de un tipo de negocio, incluyendo
     * sus templates asociados. Usado para mostrar detalles antes
     * de crear un proyecto de ese tipo específico.
     *
     * @param BusinessType $businessType Modelo inyectado por route binding
     * @return \Illuminate\Http\JsonResponse Datos completos del tipo de negocio
     */
    public function show(BusinessType $businessType)
    {
        // Verificar que el tipo de negocio esté activo y disponible
        if (!$businessType->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de negocio no disponible'
            ], 404);
        }

        // Cargar relaciones necesarias usando lazy loading
        $businessType->load(['formTemplates' => function ($query) {
            $query->where('is_default', true); // Solo template por defecto
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Tipo de negocio obtenido exitosamente',
            'data' => new BusinessTypeResource($businessType)
        ]);
    }

    /**
     * Obtiene el template de formulario por defecto de un tipo de negocio
     *
     * Este endpoint es crucial para la creación de proyectos, ya que
     * proporciona la estructura de campos que debe completar el usuario
     * según el tipo de negocio seleccionado (ej: barbería, salón, etc.).
     *
     * @param BusinessType $businessType Tipo de negocio objetivo
     * @return \Illuminate\Http\JsonResponse Template con campos predefinidos
     */
    public function getDefaultTemplate(BusinessType $businessType)
    {
        // Validar que el tipo de negocio esté disponible
        if (!$businessType->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de negocio no disponible'
            ], 404);
        }

        // Obtener template por defecto usando método del modelo
        $template = $businessType->getDefaultTemplate();

        // Verificar que existe un template configurado para este tipo
        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'No hay template por defecto para este tipo de negocio',
                'data' => [
                    'business_type' => $businessType->name,
                    'suggestion' => 'Este tipo de negocio permite formularios completamente personalizados'
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Template obtenido exitosamente',
            'data' => [
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'fields' => $template->fields, // Estructura JSON de campos del formulario
                    'fields_count' => $template->getFieldsCount(), // Número total de campos
                    'business_type' => [
                        'id' => $businessType->id,
                        'name' => $businessType->name,
                        'icon' => $businessType->icon
                    ]
                ]
            ]
        ]);
    }

    /**
     * Obtiene estadísticas de uso y popularidad de tipos de negocio
     *
     * Proporciona métricas útiles para administradores y analytics:
     * - Cuántos proyectos tiene cada tipo
     * - Cuál es el tipo más popular
     * - Distribución de uso general
     * - Score de popularidad categorizado
     *
     * @return \Illuminate\Http\JsonResponse Estadísticas completas del sistema
     */
    public function stats()
    {
        // Obtener estadísticas con conteo de proyectos relacionados
        $stats = BusinessType::active()
            ->withCount('projects') // Cuenta proyectos asociados a cada tipo
            ->orderByDesc('projects_count') // Ordenar por popularidad (más usados primero)
            ->get()
            ->map(function ($businessType) {
                return [
                    'id' => $businessType->id,
                    'name' => $businessType->name,
                    'icon' => $businessType->icon,
                    'projects_count' => $businessType->projects_count,
                    // Verificar si tiene template configurado
                    'has_template' => $businessType->formTemplates()
                        ->where('is_default', true)
                        ->exists(),
                    // Calcular score de popularidad basado en proyectos
                    'popularity_score' => $this->calculatePopularityScore($businessType->projects_count)
                ];
            });

        // Calcular métricas agregadas
        $totalProjects = $stats->sum('projects_count');
        $mostPopular = $stats->first(); // El primero es el más popular por el ordenamiento

        return response()->json([
            'success' => true,
            'message' => 'Estadísticas obtenidas exitosamente',
            'data' => [
                'business_types' => $stats, // Lista completa con estadísticas
                'summary' => [
                    'total_types' => $stats->count(), // Total de tipos activos
                    'total_projects' => $totalProjects, // Total de proyectos en sistema
                    'types_with_templates' => $stats->where('has_template', true)->count(), // Tipos con template
                    'most_popular' => $mostPopular, // Tipo de negocio más utilizado
                    // Promedio de proyectos por tipo (evitar división por cero)
                    'average_projects_per_type' => $totalProjects > 0 ? round($totalProjects / $stats->count(), 2) : 0
                ]
            ]
        ]);
    }

    /**
     * Busca tipos de negocio por nombre o descripción
     *
     * Permite a los usuarios encontrar tipos de negocio específicos
     * mediante búsqueda de texto. Útil cuando hay muchos tipos disponibles
     * o para encontrar tipos similares al negocio del usuario.
     *
     * @param Request $request Parámetros de búsqueda (query, limit)
     * @return \Illuminate\Http\JsonResponse Resultados de búsqueda filtrados
     */
    public function search(Request $request)
    {
        // Validar parámetros de entrada
        $request->validate([
            'query' => 'required|string|min:2|max:50', // Mínimo 2 caracteres para evitar búsquedas muy amplias
            'limit' => 'sometimes|integer|min:1|max:20' // Límite máximo para performance
        ]);

        $query = $request->input('query');
        $limit = $request->input('limit', 10); // Límite por defecto

        // Realizar búsqueda en nombre y descripción
        $businessTypes = BusinessType::active()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%") // Búsqueda parcial en nombre
                    ->orWhere('description', 'like', "%{$query}%"); // También en descripción
            })
            ->with(['formTemplates' => function ($q) {
                $q->where('is_default', true); // Incluir templates por defecto
            }])
            ->orderBy('name') // Ordenar alfabéticamente
            ->limit($limit) // Aplicar límite para performance
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Búsqueda completada',
            'data' => BusinessTypeResource::collection($businessTypes),
            'meta' => [
                'query' => $query, // Echo del término buscado
                'results_count' => $businessTypes->count(), // Número de resultados encontrados
                'limit' => $limit // Límite aplicado
            ]
        ]);
    }

    /**
     * Calcula un score de popularidad basado en el número de proyectos
     *
     * Método privado que categoriza los tipos de negocio según su uso:
     * - muy_alta: 100+ proyectos
     * - alta: 50-99 proyectos
     * - media: 20-49 proyectos
     * - baja: 5-19 proyectos
     * - nueva: 0-4 proyectos
     *
     * @param int $projectsCount Número de proyectos del tipo de negocio
     * @return string Nivel de popularidad categorizado
     */
    private function calculatePopularityScore(int $projectsCount): string
    {
        // Categorización basada en thresholds de uso
        if ($projectsCount >= 100) return 'muy_alta'; // Tipos muy establecidos
        if ($projectsCount >= 50) return 'alta'; // Tipos populares
        if ($projectsCount >= 20) return 'media'; // Tipos moderadamente usados
        if ($projectsCount >= 5) return 'baja'; // Tipos poco usados
        return 'nueva'; // Tipos nuevos o sin usar
    }
}
