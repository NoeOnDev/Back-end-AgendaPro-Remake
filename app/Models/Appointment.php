<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'project_id',
        'contact_id',
        'assigned_to',
        'created_by',
        'title',
        'description',
        'start_time',
        'end_time',
        'status',
        'notes',
        'metadata'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'metadata' => 'array'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'appointment_services')
            ->withPivot(['price', 'duration_minutes', 'notes'])
            ->withTimestamps();
    }

    public function appointmentForm(): HasOne
    {
        return $this->hasOne(AppointmentForm::class);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('start_time', today());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>=', now());
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('assigned_to', $user->id);
    }

    public function getTotalPriceAttribute(): float
    {
        return $this->services->sum('pivot.price');
    }

    public function getTotalDurationAttribute(): int
    {
        return $this->services->sum('pivot.duration_minutes');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed' && $this->appointmentForm()->exists();
    }

    public function getDurationInMinutesAttribute(): int
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }

    public function isToday(): bool
    {
        return $this->start_time->isToday();
    }

    public function isPast(): bool
    {
        return $this->start_time->isPast();
    }

    public function isFuture(): bool
    {
        return $this->start_time->isFuture();
    }
}
