<?php

namespace Tests\Feature\ThirdParties;

use App\Modules\Advisors\Models\Advisor;
use App\Modules\Billing\Models\BillInvoice;
use App\Modules\ThirdParties\Models\AdvisorReceivable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivableTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_and_update_receivable_status(): void
    {
        $advisor = Advisor::query()->create([
            'code' => 'AS-R1',
            'first_name' => 'R',
            'commission_new' => 0,
            'commission_recurring' => 0,
            'authorizes_credits' => true,
        ]);

        $invoice = BillInvoice::query()->create([
            'affiliate_id' => null,
            'payer_id' => null,
            'tipo' => '03',
            'payment_method' => 'CREDITO',
            'total_pesos' => 200_000,
            'estado' => 'ACTIVO',
        ]);

        $rec = AdvisorReceivable::query()->create([
            'advisor_id' => $advisor->id,
            'bill_invoice_id' => $invoice->id,
            'amount_pesos' => 200_000,
            'status' => 'PENDIENTE',
        ]);

        $this->getJson('/api/third-parties/advisor-receivables?advisor_id='.$advisor->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->patchJson('/api/third-parties/advisor-receivables/'.$rec->id, [
            'status' => 'PAGADA',
        ])->assertOk()->assertJsonPath('status', 'PAGADA');

        $this->assertSame('PAGADA', AdvisorReceivable::query()->find($rec->id)?->status);
    }
}
