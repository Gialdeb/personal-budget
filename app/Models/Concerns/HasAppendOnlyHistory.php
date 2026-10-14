<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

trait HasAppendOnlyHistory
{
    public static function bootHasAppendOnlyHistory(): void
    {
        static::updating(fn (Model $model) => throw new LogicException('Historical counterparty records are append-only.'));
        static::deleting(fn (Model $model) => throw new LogicException('Historical counterparty records cannot be deleted.'));
    }
}
