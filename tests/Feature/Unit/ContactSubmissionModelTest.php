<?php

namespace Tests\Feature\Unit;

use App\Models\ContactSubmission;
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
}
