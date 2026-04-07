<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsModelActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->getActivityLogName())
            ->logOnly($this->getActivityLogAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return sprintf('%s %s', $eventName, class_basename(static::class));
    }

    protected function getActivityLogName(): string
    {
        return strtolower(class_basename(static::class));
    }

    /**
     * @return array<int, string>
     */
    protected function getActivityLogAttributes(): array
    {
        return array_values(array_diff($this->getFillable(), $this->getActivityLogExcludedAttributes()));
    }

    /**
     * @return array<int, string>
     */
    protected function getActivityLogExcludedAttributes(): array
    {
        return method_exists($this, 'getHidden') ? $this->getHidden() : [];
    }
}
