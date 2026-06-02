<?php

namespace App\Support;

use App\Models\SiteSetting;
use Carbon\CarbonInterface;

class SalesforcePlantSyncSchedule
{
    public const FALLBACK_INTERVAL_MINUTES = 5;

    public static function shouldRunAt(?CarbonInterface $moment = null): bool
    {
        $reference = $moment ?? now();
        $intervalMinutes = self::resolveIntervalMinutes();

        $minuteOfDay = ((int) $reference->format('H') * 60) + (int) $reference->format('i');

        return $minuteOfDay % $intervalMinutes === 0;
    }

    public static function resolveIntervalMinutes(): int
    {
        $configuredInterval = SiteSetting::get('salesforce_sync_interval_minutes', self::FALLBACK_INTERVAL_MINUTES);

        if (! is_numeric($configuredInterval)) {
            return self::FALLBACK_INTERVAL_MINUTES;
        }

        $minutes = (int) $configuredInterval;

        if ($minutes < self::FALLBACK_INTERVAL_MINUTES) {
            return self::FALLBACK_INTERVAL_MINUTES;
        }

        return $minutes;
    }
}
