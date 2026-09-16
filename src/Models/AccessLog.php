<?php

namespace LaraUtilX\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class AccessLog extends Model
{
    use Prunable;

    protected $guarded = ['id'];

    /**
     * Access logs accumulate on every request, so `php artisan model:prune`
     * drops anything older than the configured retention. Set the retention to
     * null to keep rows indefinitely.
     */
    public function prunable(): Builder
    {
        $days = config('lara-util-x.access_log.retention_days', 30);

        if ($days === null) {
            // Matches nothing, so scheduled pruning becomes a no-op.
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where('created_at', '<=', now()->subDays((int) $days));
    }
}
