<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'business_type_id',
        'status',
        'settings'
    ];

    protected $casts = [
        'settings' => 'array'
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_users')
            ->withPivot(['role_id', 'status', 'invited_by', 'invited_at', 'joined_at', 'expires_at'])
            ->withTimestamps();
    }

    public function projectUsers(): HasMany
    {
        return $this->hasMany(ProjectUser::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function hasUser(User $user): bool
    {
        return $this->isOwner($user) ||
            $this->users()->where('user_id', $user->id)->exists();
    }

    public function getUserRole(User $user): ?Role
    {
        if ($this->isOwner($user)) {
            return Role::where('name', 'owner')->first();
        }

        $projectUser = $this->projectUsers()
            ->where('user_id', $user->id)
            ->with('role')
            ->first();

        return $projectUser?->role;
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function projectForms(): HasMany
    {
        return $this->hasMany(ProjectForm::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class);
    }

    public function getActiveServicesAttribute()
    {
        return $this->services()->active()->orderBy('sort_order')->get();
    }

    public function getActiveFormsAttribute()
    {
        return $this->projectForms()->active()->get();
    }
}
