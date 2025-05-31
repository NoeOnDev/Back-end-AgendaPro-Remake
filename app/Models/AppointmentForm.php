<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'project_form_id',
        'form_data',
        'completed_by',
        'completed_at'
    ];

    protected $casts = [
        'form_data' => 'array',
        'completed_at' => 'datetime'
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function projectForm(): BelongsTo
    {
        return $this->belongsTo(ProjectForm::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function getFieldValue(string $fieldName): mixed
    {
        return $this->form_data[$fieldName] ?? null;
    }
}
