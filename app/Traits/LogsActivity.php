<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    protected static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            $model->logActivity('created');
        });

        static::updated(function (Model $model) {
            $model->logActivity('updated');
        });

        static::deleted(function (Model $model) {
            $model->logActivity('deleted');
        });
    }

    public function logActivity(string $action, array $customData = []): void
    {
        if (!auth()->check()) {
            return;
        }

        $projectId = $this->getProjectIdForLogging();

        if (!$projectId) {
            return;
        }

        $oldValues = [];
        $newValues = [];

        if ($action === 'updated' && $this->wasChanged()) {
            $oldValues = array_intersect_key($this->getOriginal(), $this->getChanges());
            $newValues = $this->getChanges();
        } elseif ($action === 'created') {
            $newValues = $this->getAttributes();
        }

        ActivityLog::create([
            'project_id' => $projectId,
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => get_class($this),
            'model_id' => $this->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            ...$customData
        ]);
    }

    protected function getProjectIdForLogging(): ?int
    {
        if (isset($this->attributes['project_id'])) {
            return $this->attributes['project_id'];
        }

        if ($this instanceof \App\Models\Project) {
            return $this->id;
        }

        if (method_exists($this, 'project')) {
            return $this->project?->id;
        }

        return null;
    }
}
