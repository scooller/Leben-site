<?php

namespace Tests\Feature\Unit;

use App\Models\ContactSubmission;
use Carbon\Carbon;
use Tests\TestCase;

class ContactSubmissionModelTest extends TestCase
{
    public function test_it_builds_the_salesforce_lead_url(): void
    {
        config()->set('services.salesforce.instance_url', 'https://leben.lightning.force.com');

        $submission = new ContactSubmission([
            'salesforce_case_id' => '00QU100000WVSFKMA5',
        ]);

        $this->assertSame(
            'https://leben.lightning.force.com/lightning/r/Lead/00QU100000WVSFKMA5/view',
            $submission->salesforceLeadUrl(),
        );
    }

    public function test_it_returns_null_when_lead_id_is_missing_or_invalid(): void
    {
        config()->set('services.salesforce.instance_url', 'https://leben.lightning.force.com');

        $missingLeadId = new ContactSubmission([
            'salesforce_case_id' => null,
        ]);

        $invalidLeadId = new ContactSubmission([
            'salesforce_case_id' => 'INVALID_ID',
        ]);

        $this->assertNull($missingLeadId->salesforceLeadUrl());
        $this->assertNull($invalidLeadId->salesforceLeadUrl());
    }

    public function test_it_returns_null_when_salesforce_instance_url_is_not_configured(): void
    {
        config()->set('services.salesforce.instance_url', null);

        $submission = new ContactSubmission([
            'salesforce_case_id' => '00QU100000WVSFKMA5',
        ]);

        $this->assertNull($submission->salesforceLeadUrl());
    }

    public function test_it_returns_null_sync_metadata_when_not_synced(): void
    {
        $submission = new ContactSubmission([
            'salesforce_case_id' => null,
            'salesforce_case_error' => null,
        ]);

        $this->assertFalse($submission->hasSalesforceSyncInfo());
        $this->assertNull($submission->salesforceSyncedAt());
        $this->assertNull($submission->salesforceSyncModeLabel());
    }

    public function test_it_infers_automatic_sync_when_update_happens_near_submission_time(): void
    {
        $submittedAt = Carbon::parse('2026-04-28 10:00:00');
        $updatedAt = Carbon::parse('2026-04-28 10:00:20');

        $submission = new ContactSubmission([
            'salesforce_case_id' => '00QU100000WVSFKMA5',
            'submitted_at' => $submittedAt,
        ]);
        $submission->updated_at = $updatedAt;

        $this->assertTrue($submission->hasSalesforceSyncInfo());
        $this->assertTrue($updatedAt->equalTo($submission->salesforceSyncedAt()));
        $this->assertSame('Automática', $submission->salesforceSyncModeLabel());
    }

    public function test_it_infers_manual_sync_when_update_happens_long_after_submission_time(): void
    {
        $submittedAt = Carbon::parse('2026-04-28 10:00:00');
        $updatedAt = Carbon::parse('2026-04-28 10:05:00');

        $submission = new ContactSubmission([
            'salesforce_case_id' => '00QU100000WVSFKMA5',
            'submitted_at' => $submittedAt,
        ]);
        $submission->updated_at = $updatedAt;

        $this->assertSame('Manual', $submission->salesforceSyncModeLabel());
    }

    public function test_it_prefers_explicit_sync_metadata_when_available(): void
    {
        $submittedAt = Carbon::parse('2026-04-28 10:00:00');
        $syncedAt = Carbon::parse('2026-04-28 10:00:03');

        $submission = new ContactSubmission([
            'salesforce_case_id' => '00QU100000WVSFKMA5',
            'submitted_at' => $submittedAt,
            'salesforce_synced_at' => $syncedAt,
            'salesforce_sync_trigger' => 'manual',
        ]);
        $submission->updated_at = Carbon::parse('2026-04-28 10:00:05');

        $this->assertTrue($syncedAt->equalTo($submission->salesforceSyncedAt()));
        $this->assertSame('Manual', $submission->salesforceSyncModeLabel());
    }

    public function test_it_normalizes_phone_to_digits_only(): void
    {
        $submission = new ContactSubmission;
        $submission->phone = '+56 9 8202 1604';

        $this->assertSame('56982021604', $submission->phone);

        $submission->phone = '---';
        $this->assertNull($submission->phone);
    }
}
