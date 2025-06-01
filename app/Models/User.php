<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_users')
            ->withPivot(['role_id', 'status', 'invited_by', 'invited_at', 'joined_at', 'expires_at'])
            ->withTimestamps();
    }

    public function projectUsers(): HasMany
    {
        return $this->hasMany(ProjectUser::class);
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class, 'invited_by');
    }

    // Relaciones para contactos y citas
    public function createdContacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'created_by');
    }

    public function assignedAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'assigned_to');
    }

    public function createdAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'created_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // Métodos utilitarios
    public function getAllProjects()
    {
        return Project::where('owner_id', $this->id)
            ->orWhereHas('users', function ($query) {
                $query->where('user_id', $this->id)
                    ->where('status', 'active');
            })
            ->get();
    }

    public function hasAccessToProject(Project $project): bool
    {
        return $project->owner_id === $this->id ||
            $project->users()->where('user_id', $this->id)->exists();
    }

    public function getRoleInProject(Project $project): ?Role
    {
        return $project->getUserRole($this);
    }

    public function hasPermissionInProject(Project $project, string $resource, string $action): bool
    {
        $role = $this->getRoleInProject($project);
        return $role?->hasPermission($resource, $action) ?? false;
    }
}
