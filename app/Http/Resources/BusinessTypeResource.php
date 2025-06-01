<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para transformar datos de BusinessType
 */
class BusinessTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'is_active' => $this->is_active,

            // Conteo de proyectos si está disponible
            'projects_count' => $this->whenCounted('projects'),

            // Información de template por defecto
            'has_default_template' => $this->when(
                $this->relationLoaded('formTemplates'),
                fn() => $this->formTemplates->where('is_default', true)->isNotEmpty()
            ),

            'default_template' => $this->when(
                $this->relationLoaded('formTemplates'),
                function () {
                    $template = $this->formTemplates->where('is_default', true)->first();
                    return $template ? [
                        'id' => $template->id,
                        'name' => $template->name,
                        'description' => $template->description,
                        'fields_count' => $template->getFieldsCount(),
                        'preview_fields' => collect($template->fields)
                            ->take(3)
                            ->map(function ($field) {
                                return [
                                    'name' => $field['name'] ?? '',
                                    'label' => $field['label'] ?? '',
                                    'type' => $field['type'] ?? 'text',
                                    'required' => $field['required'] ?? false
                                ];
                            })
                    ] : null;
                }
            ),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Metadatos adicionales
            'meta' => [
                'can_customize' => true,
                'recommended_for' => $this->getRecommendedFor(),
                'setup_difficulty' => $this->getSetupDifficulty()
            ]
        ];
    }

    /**
     * Obtener recomendaciones para este tipo de negocio
     */
    private function getRecommendedFor(): array
    {
        return match ($this->name) {
            'Barbería' => ['Peluquerías', 'Salones masculinos', 'Barberías tradicionales'],
            'Salón de Belleza' => ['Estéticas', 'Spas', 'Centros de belleza'],
            'Consultorio Médico' => ['Consultas privadas', 'Clínicas', 'Especialistas'],
            'Consultorio Dental' => ['Dentistas', 'Ortodoncistas', 'Odontólogos'],
            'Veterinaria' => ['Clínicas veterinarias', 'Hospitales de mascotas'],
            default => ['Negocios de servicios', 'Consultorios', 'Centros especializados']
        };
    }

    /**
     * Obtener dificultad de configuración
     */
    private function getSetupDifficulty(): string
    {
        $hasTemplate = $this->formTemplates->where('is_default', true)->isNotEmpty();
        return $hasTemplate ? 'facil' : 'personalizable';
    }
}
