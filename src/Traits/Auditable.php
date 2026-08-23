<?php

namespace LaraUtilX\Traits;

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

    /**
     * Attributes that never reach the audit trail. Override on the model to add
     * your own on top of the configured defaults.
     */
    protected static function auditExcludedAttributes(): array
    {
        return config('lara-util-x.audit.excluded_attributes', []);
    }

    private static function logAudit(Model $model, string $event, array $oldValues = [], array $newValues = []): void
    {
        DB::table(config('lara-util-x.audit.table', 'model_audits'))->insert([
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'event' => $event,
            'old_values' => json_encode(self::withoutExcludedAttributes($oldValues)),
            'new_values' => json_encode(self::withoutExcludedAttributes($newValues)),
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function withoutExcludedAttributes(array $values): array
    {
        return array_diff_key($values, array_flip(static::auditExcludedAttributes()));
    }
}
