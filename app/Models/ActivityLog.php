<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    public function scopeForProject($query, Project $project)
    {
        return $query->where('project_id', $project->id);
    }

    public function scopeByUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByModel($query, string $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function getActionDisplayAttribute(): string
    {
        return match ($this->action) {
            'created' => 'Creado',
            'updated' => 'Actualizado',
            'deleted' => 'Eliminado',
            'invited' => 'Invitado',
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'assigned' => 'Asignado',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            default => ucfirst($this->action)
        };
    }

    public function getModelDisplayAttribute(): string
    {
        return match ($this->model_type) {
            'App\\Models\\Contact' => 'Contacto',
            'App\\Models\\Appointment' => 'Cita',
            'App\\Models\\Project' => 'Proyecto',
            'App\\Models\\ProjectUser' => 'Usuario de proyecto',
            'App\\Models\\ProjectInvitation' => 'Invitación',
            default => class_basename($this->model_type)
        };
    }

    public function hasLoggedChanges(): bool
    {
        return !empty($this->old_values) || !empty($this->new_values);
    }

    public function getChangedFields(): array
    {
        if (!$this->hasLoggedChanges()) {
            return [];
        }

        $oldValues = $this->old_values ?? [];
        $newValues = $this->new_values ?? [];

        return array_keys(array_merge($oldValues, $newValues));
    }

    public function getFieldChange(string $field): ?array
    {
        $oldValues = $this->old_values ?? [];
        $newValues = $this->new_values ?? [];

        if (!isset($oldValues[$field]) && !isset($newValues[$field])) {
            return null;
        }

        return [
            'old' => $oldValues[$field] ?? null,
            'new' => $newValues[$field] ?? null
        ];
    }
}
