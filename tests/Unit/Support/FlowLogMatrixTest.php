<?php

namespace Tests\Unit\Support;

use App\Support\FlowLogMatrix;
use Tests\TestCase;

class FlowLogMatrixTest extends TestCase
{
    public function test_it_resolves_expected_levels_for_known_events(): void
    {
        $this->assertSame('info', FlowLogMatrix::levelFor('payments.transbank.payment_completed'));
        $this->assertSame('info', FlowLogMatrix::levelFor('payments.transbank.create_transaction_success'));
        $this->assertSame('error', FlowLogMatrix::levelFor('payments.transbank.create_transaction_error'));
        $this->assertSame('info', FlowLogMatrix::levelFor('payments.mercadopago.create_preference_success'));
        $this->assertSame('warning', FlowLogMatrix::levelFor('payments.mercadopago.signature_invalid'));
        $this->assertSame('critical', FlowLogMatrix::levelFor('salesforce.job.invalid_grant'));
        $this->assertSame('warning', FlowLogMatrix::levelFor('production_sync.http_unsuccessful'));
    }

    public function test_it_returns_info_for_unknown_events(): void
    {
        $this->assertSame('info', FlowLogMatrix::levelFor('unknown.flow.event'));
    }
}
