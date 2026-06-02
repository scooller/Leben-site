<?php

namespace Tests\Unit\Support;

use App\Models\SiteSetting;
use App\Support\SalesforcePlantSyncSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesforcePlantSyncScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_on_the_configured_interval(): void
    {
        SiteSetting::current()->update([
            'salesforce_sync_interval_minutes' => 15,
        ]);

        $this->assertTrue(SalesforcePlantSyncSchedule::shouldRunAt(CarbonImmutable::parse('2026-06-02 10:30:00')));
        $this->assertFalse(SalesforcePlantSyncSchedule::shouldRunAt(CarbonImmutable::parse('2026-06-02 10:31:00')));
    }

    public function test_it_falls_back_to_five_minutes_when_setting_is_invalid(): void
    {
        SiteSetting::current()->update([
            'salesforce_sync_interval_minutes' => 0,
        ]);

        $this->assertSame(5, SalesforcePlantSyncSchedule::resolveIntervalMinutes());
        $this->assertTrue(SalesforcePlantSyncSchedule::shouldRunAt(CarbonImmutable::parse('2026-06-02 10:10:00')));
        $this->assertFalse(SalesforcePlantSyncSchedule::shouldRunAt(CarbonImmutable::parse('2026-06-02 10:11:00')));
    }

    public function test_it_falls_back_to_five_minutes_when_setting_is_lower_than_five(): void
    {
        SiteSetting::current()->update([
            'salesforce_sync_interval_minutes' => 1,
        ]);

        $this->assertSame(5, SalesforcePlantSyncSchedule::resolveIntervalMinutes());
        $this->assertTrue(SalesforcePlantSyncSchedule::shouldRunAt(CarbonImmutable::parse('2026-06-02 10:20:00')));
        $this->assertFalse(SalesforcePlantSyncSchedule::shouldRunAt(CarbonImmutable::parse('2026-06-02 10:22:00')));
    }
}
