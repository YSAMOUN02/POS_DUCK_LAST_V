<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    protected static function bootLogsActivity()
    {
        static::created(function ($model) {
            static::writeActivityLog('created', $model, null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changes);
            static::writeActivityLog('updated', $model, $old, $changes);
        });

        static::deleted(function ($model) {
            static::writeActivityLog('deleted', $model, $model->getOriginal(), null);
        });
    }

    protected static function writeActivityLog(string $action, $model, ?array $old, ?array $new)
    {
        $sensitive = ['password', 'remember_token'];

        $old = $old ? array_diff_key($old, array_flip($sensitive)) : $old;
        $new = $new ? array_diff_key($new, array_flip($sensitive)) : $new;

        if (($old === null || $old === []) && ($new === null || $new === [])) {
            return;
        }

        ActivityLog::create([
            'user_id'    => Auth::id(),
            'user_name'  => Auth::user()->name ?? 'System',
            'action'     => $action,
            'model_type' => class_basename($model),
            'model_id'   => $model->getKey(),
            'section'    => property_exists($model, 'activitySection') ? $model->activitySection : null,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
        ]);
    }
}
