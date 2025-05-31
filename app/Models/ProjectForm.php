<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectForm extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'fields',
        'is_active',
        'created_from_template_id',
        'created_by'
    ];

    protected $casts = [
        'fields' => 'array',
        'is_active' => 'boolean'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'created_from_template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appointmentForms(): HasMany
    {
        return $this->hasMany(AppointmentForm::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
