<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_type_id',
        'name',
        'description',
        'fields',
        'is_default'
    ];

    protected $casts = [
        'fields' => 'array',
        'is_default' => 'boolean'
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function projectForms(): HasMany
    {
        return $this->hasMany(ProjectForm::class, 'created_from_template_id');
    }

    public function getFieldsCount(): int
    {
        return count($this->fields ?? []);
    }
}
