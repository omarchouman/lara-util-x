<?php

namespace omarchouman\LaraUtilX\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            self::logAudit($model, 'created', [], $model->getAttributes());
        });

        static::updated(function (Model $model) {
            self::logAudit($model, 'updated', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function (Model $model) {
            self::logAudit($model, 'deleted', $model->getOriginal());
        });
    }

    private static function logAudit(Model $model, string $event, array $oldValues = [], array $newValues = []): void
    {
        DB::table('model_audits')->insert([
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'event' => $event,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($newValues),
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
