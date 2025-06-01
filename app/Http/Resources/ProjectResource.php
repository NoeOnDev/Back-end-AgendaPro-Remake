<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para transformar datos de Project
 */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'settings' => $this->settings,

            // Información del owner
            'owner' => $this->when(
                $this->relationLoaded('owner'),
                fn() => [
                    'id' => $this->owner->id,
                    'name' => $this->owner->name,
                    'email' => $this->owner->email,
                    'avatar' => $this->owner->avatar ? url('storage/avatars/' . $this->owner->avatar) : null
                ]
            ),

            // Tipo de negocio
            'business_type' => $this->when(
                $this->relationLoaded('businessType'),
                fn() => [
                    'id' => $this->businessType->id,
                    'name' => $this->businessType->name,
                    'description' => $this->businessType->description,
                    'icon' => $this->businessType->icon
                ]
            ),

            // Miembros del equipo
            'team_members' => $this->when(
                $this->relationLoaded('users'),
                fn() => $this->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar ? url('storage/avatars/' . $user->avatar) : null,
                        'role' => $user->pivot->role ? [
                            'id' => $user->pivot->role->id,
                            'name' => $user->pivot->role->name,
                            'display_name' => $user->pivot->role->display_name
                        ] : null,
                        'status' => $user->pivot->status,
                        'joined_at' => $user->pivot->joined_at
                    ];
                })
            ),

            // Formularios del proyecto
            'forms' => $this->when(
                $this->relationLoaded('projectForms'),
                fn() => $this->projectForms->map(function ($form) {
                    return [
                        'id' => $form->id,
                        'name' => $form->name,
                        'description' => $form->description,
                        'fields_count' => count($form->fields ?? []),
                        'is_active' => $form->is_active,
                        'created_from_template' => $form->template ? [
                            'id' => $form->template->id,
                            'name' => $form->template->name
                        ] : null
                    ];
                })
            ),

            // Servicios activos
            'services' => $this->when(
                $this->relationLoaded('services'),
                fn() => $this->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'description' => $service->description,
                        'duration_minutes' => $service->duration_minutes,
                        'duration_display' => $service->duration_display,
                        'price' => $service->price,
                        'sort_order' => $service->sort_order
                    ];
                })
            ),

            // Tags disponibles
            'tags' => $this->when(
                $this->relationLoaded('tags'),
                fn() => $this->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color,
                        'description' => $tag->description,
                        'contacts_count' => $tag->contacts_count ?? 0
                    ];
                })
            ),

            // Horarios de trabajo
            'working_hours' => $this->when(
                $this->relationLoaded('workingHours'),
                fn() => $this->workingHours->groupBy('day_of_week')->map(function ($hours, $day) {
                    return [
                        'day' => $day,
                        'day_name' => $hours->first()->day_name,
                        'schedules' => $hours->map(function ($hour) {
                            return [
                                'id' => $hour->id,
                                'user_id' => $hour->user_id,
                                'user_name' => $hour->user?->name,
                                'start_time' => $hour->start_time->format('H:i'),
                                'end_time' => $hour->end_time->format('H:i'),
                                'is_active' => $hour->is_active
                            ];
                        })
                    ];
                })
            ),

            // Conteos
            'contacts_count' => $this->whenCounted('contacts'),
            'appointments_count' => $this->whenCounted('appointments'),
            'upcoming_appointments_count' => $this->whenCounted('upcoming_appointments'),
            'completed_appointments_count' => $this->whenCounted('completed_appointments'),
            'active_members_count' => $this->whenCounted('active_members'),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Metadatos útiles
            'meta' => [
                'is_setup_complete' => $this->isSetupComplete(),
                'next_steps' => $this->getNextSteps(),
                'health_score' => $this->getHealthScore()
            ]
        ];
    }

    /**
     * Verificar si el proyecto está completamente configurado
     */
    private function isSetupComplete(): bool
    {
        return $this->services()->exists() &&
            $this->projectForms()->where('is_active', true)->exists();
    }

    /**
     * Obtener próximos pasos recomendados
     */
    private function getNextSteps(): array
    {
        $steps = [];

        if (!$this->services()->exists()) {
            $steps[] = 'add_services';
        }

        if (!$this->projectForms()->where('is_active', true)->exists()) {
            $steps[] = 'setup_forms';
        }

        if (!$this->users()->where('status', 'active')->exists()) {
            $steps[] = 'invite_team';
        }

        if (!$this->workingHours()->exists()) {
            $steps[] = 'configure_schedule';
        }

        return $steps;
    }

    /**
     * Calcular score de salud del proyecto
     */
    private function getHealthScore(): int
    {
        $score = 0;
        $maxScore = 100;

        // Tiene servicios (25%)
        if ($this->services()->exists()) $score += 25;

        // Tiene formularios activos (25%)
        if ($this->projectForms()->where('is_active', true)->exists()) $score += 25;

        // Tiene contactos (20%)
        if ($this->contacts()->exists()) $score += 20;

        // Tiene citas (20%)
        if ($this->appointments()->exists()) $score += 20;

        // Tiene horarios configurados (10%)
        if ($this->workingHours()->exists()) $score += 10;

        return min($score, $maxScore);
    }
}
