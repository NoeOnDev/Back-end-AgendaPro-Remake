<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'type',
        'minutes_before',
        'status',
        'sent_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime'
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeDue($query)
    {
        return $query->whereHas('appointment', function ($q) {
            $q->where('start_time', '<=', now()->addMinutes($this->minutes_before));
        })->where('status', 'pending');
    }

    public function isDue(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $reminderTime = $this->appointment->start_time->subMinutes($this->minutes_before);
        return now()->greaterThanOrEqualTo($reminderTime);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'sent_at' => now()
        ]);
    }

    public function getTypeDisplayAttribute(): string
    {
        return match ($this->type) {
            'email' => 'Correo electrónico',
            'sms' => 'SMS',
            'notification' => 'Notificación',
            default => $this->type
        };
    }

    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pendiente',
            'sent' => 'Enviado',
            'failed' => 'Fallido',
            default => $this->status
        };
    }
}
