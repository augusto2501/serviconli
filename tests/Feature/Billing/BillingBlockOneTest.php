<?php

namespace Tests\Feature\Billing;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliations\Models\Payer;
use App\Modules\Billing\Enums\CancellationReason;
use App\Modules\Billing\Models\BillInvoice;
use App\Modules\Billing\Models\CuentaCobro;
use App\Modules\Billing\Models\CuentaCobroDetail;
use App\Modules\Billing\Models\PaymentReceived;
use App\Modules\Billing\Services\CuentaCobroService;
use App\Modules\Billing\Services\InvoiceCancellationService;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\PILALiquidation\Strategies\ConsignacionPaymentStrategy;
use App\Modules\PILALiquidation\Strategies\CreditoPaymentStrategy;
use App\Modules\PILALiquidation\Strategies\EfectivoPaymentStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingBlockOneTest extends TestCase
{
    use RefreshDatabase;

    private function createAffiliate(): Affiliate
    {
        $person = Person::query()->create([
            'document_number' => 'B'.random_int(100000, 999999),
            'first_name' => 'Test', 'first_surname' => 'Bill',
            'gender' => 'M', 'address' => 'Calle 1', 'cellphone' => '300111',
        ]);

        return Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);
    }

    private function createLiquidation(Affiliate $affiliate, int $total = 150000): PilaLiquidation
    {
        return PilaLiquidation::query()->create([
            'affiliate_id' => $affiliate->id,
            'public_id' => 'PL-'.random_int(1000, 9999),
            'contributor_type_code' => '03',
            'arl_risk_class' => 1,
            'payment_date' => now(),
            'document_last_two_digits' => 1,
            'total_social_security_pesos' => $total,
            'subsystem_totals_pesos' => json_encode(['health' => $total]),
            'status' => 'confirmed',
        ]);
    }

    // ── RF-079: PDF cuenta de cobro ──

    public function test_cuenta_cobro_pdf_route_exists(): void
    {
        $payer = Payer::query()->create([
            'razon_social' => 'Empresa Test', 'nit_body' => '900123456',
            'digito_verificacion' => '1',
        ]);

        $cuenta = CuentaCobro::query()->create([
            'payer_id' => $payer->id, 'cuenta_number' => 'CC-2026-0001',
            'period_year' => 2026, 'period_month' => 4, 'period_cobro' => '2026-04',
            'period_servicio' => '2026-04', 'generation_mode' => 'PLENO',
            'total_eps' => 50000, 'total_afp' => 40000, 'total_arl' => 5000,
            'total_ccf' => 3000, 'total_admin' => 20000, 'total_affiliation' => 10000,
            'total_1' => 128000, 'status' => 'DEFINITIVA',
        ]);

        $response = $this->getJson("/api/cuentas-cobro/{$cuenta->id}/pdf");
        $response->assertStatus(200);
    }

    // ── RF-084: catálogo causales anulación ──

    public function test_cancellation_rejects_invalid_reason(): void
    {
        $affiliate = $this->createAffiliate();
        $invoice = BillInvoice::query()->create([
            'public_number' => 'RC-2026-0001', 'affiliate_id' => $affiliate->id,
            'tipo' => 'APORTE_INDIVIDUAL', 'payment_method' => 'EFECTIVO',
            'total_pesos' => 100000, 'estado' => 'PAGADO',
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/cancel", [
            'cancellation_reason' => 'MOTIVO_INVENTADO',
            'cancellation_motive' => 'test',
        ]);

        $response->assertStatus(422);
    }

    public function test_cancellation_accepts_valid_reason(): void
    {
        $affiliate = $this->createAffiliate();
        $invoice = BillInvoice::query()->create([
            'public_number' => 'RC-2026-0002', 'affiliate_id' => $affiliate->id,
            'tipo' => 'APORTE_INDIVIDUAL', 'payment_method' => 'EFECTIVO',
            'total_pesos' => 100000, 'estado' => 'PAGADO',
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/cancel", [
            'cancellation_reason' => CancellationReason::ERROR_DIGITACION->value,
            'cancellation_motive' => 'Error en el monto',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('bill_invoices', [
            'id' => $invoice->id, 'estado' => 'ANULADO',
        ]);
    }

    // ── RF-085: bloqueo anulación con aportes ──

    public function test_cancellation_blocked_for_affiliation_with_contributions(): void
    {
        $affiliate = $this->createAffiliate();
        $this->createLiquidation($affiliate);

        $invoice = BillInvoice::query()->create([
            'public_number' => 'RC-2026-0003', 'affiliate_id' => $affiliate->id,
            'tipo' => 'AFILIACION', 'payment_method' => 'EFECTIVO',
            'total_pesos' => 50000, 'estado' => 'PAGADO',
        ]);

        $service = app(InvoiceCancellationService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RN-26');
        $service->cancel($invoice, 'ERROR_DIGITACION', 'Test', 'admin');
    }

    public function test_reingreso_also_blocked_with_contributions(): void
    {
        $affiliate = $this->createAffiliate();
        $this->createLiquidation($affiliate);

        $invoice = BillInvoice::query()->create([
            'public_number' => 'RC-2026-0004', 'affiliate_id' => $affiliate->id,
            'tipo' => 'REINGRESO', 'payment_method' => 'EFECTIVO',
            'total_pesos' => 50000, 'estado' => 'PAGADO',
        ]);

        $service = app(InvoiceCancellationService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->cancel($invoice, 'ERROR_DIGITACION', 'Test', 'admin');
    }

    // ── RF-086: cascada con depósito bancario ──

    public function test_cancellation_reverts_bank_deposit_status(): void
    {
        $affiliate = $this->createAffiliate();
        $invoice = BillInvoice::query()->create([
            'public_number' => 'RC-2026-0005', 'affiliate_id' => $affiliate->id,
            'tipo' => 'APORTE_INDIVIDUAL', 'payment_method' => 'CONSIGNACION',
            'total_pesos' => 100000, 'estado' => 'PENDIENTE_CRUCE',
        ]);

        \DB::table('tp_bank_deposits')->insert([
            'invoice_id' => $invoice->id, 'bank_name' => 'Bancolombia',
            'reference' => 'REF123', 'amount_pesos' => 100000,
            'deposit_type' => 'LOCAL', 'status' => 'ACTIVO',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(InvoiceCancellationService::class);
        $service->cancel($invoice, 'DUPLICADO', 'Consignación duplicada', 'admin');

        $this->assertDatabaseHas('tp_bank_deposits', [
            'invoice_id' => $invoice->id, 'status' => 'ANULADO',
        ]);
    }

    // ── RF-086: cascada anula comisión asesor ──

    public function test_cancellation_reverts_advisor_commission(): void
    {
        $affiliate = $this->createAffiliate();

        $advisorId = \DB::table('sec_advisors')->insertGetId([
            'code' => 'ADV-001', 'first_name' => 'Asesor',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        \DB::table('bill_advisor_commissions')->insert([
            'affiliate_id' => $affiliate->id, 'public_number' => 'CE-2026-0001',
            'advisor_id' => $advisorId, 'amount_pesos' => 30000,
            'commission_type' => 'AFILIACION',
            'status' => 'CALCULADA', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $invoice = BillInvoice::query()->create([
            'public_number' => 'RC-2026-0006', 'affiliate_id' => $affiliate->id,
            'tipo' => 'AFILIACION', 'payment_method' => 'EFECTIVO',
            'total_pesos' => 50000, 'estado' => 'PAGADO',
        ]);

        $service = app(InvoiceCancellationService::class);
        $service->cancel($invoice, 'ERROR_DIGITACION', 'Test', 'admin');

        $this->assertDatabaseHas('bill_advisor_commissions', [
            'affiliate_id' => $affiliate->id, 'status' => 'ANULADA',
        ]);
    }

    // ── RF-075/087: Strategies crean PaymentReceived ──

    public function test_efectivo_strategy_creates_payment_received(): void
    {
        $affiliate = $this->createAffiliate();
        $liquidation = $this->createLiquidation($affiliate);

        $strategy = new EfectivoPaymentStrategy;
        $result = $strategy->process($liquidation);

        $this->assertStringStartsWith('RC-', $result['receipt_id']);
        $this->assertDatabaseHas('bill_payments_received', [
            'invoice_id' => $result['extra']['invoice_id'],
            'payment_method' => 'EFECTIVO',
            'status' => 'ACTIVO',
        ]);
    }

    public function test_consignacion_strategy_creates_deposit_and_payment(): void
    {
        $affiliate = $this->createAffiliate();
        $liquidation = $this->createLiquidation($affiliate, 200000);

        $strategy = new ConsignacionPaymentStrategy;
        $result = $strategy->process($liquidation, [
            'bank_name' => 'Bancolombia',
            'bank_reference' => 'REF-UNIQUE-001',
            'deposit_type' => 'NACIONAL',
        ]);

        $this->assertStringStartsWith('RC-', $result['receipt_id']);
        $this->assertDatabaseHas('tp_bank_deposits', [
            'invoice_id' => $result['extra']['invoice_id'],
            'bank_name' => 'Bancolombia',
            'reference' => 'REF-UNIQUE-001',
            'status' => 'ACTIVO',
        ]);
        $this->assertDatabaseHas('bill_payments_received', [
            'invoice_id' => $result['extra']['invoice_id'],
            'payment_method' => 'CONSIGNACION',
            'status' => 'PENDIENTE',
        ]);
    }

    public function test_credito_strategy_creates_account_receivable(): void
    {
        $affiliate = $this->createAffiliate();
        $liquidation = $this->createLiquidation($affiliate);

        $strategy = new CreditoPaymentStrategy;
        $result = $strategy->process($liquidation);

        $this->assertDatabaseHas('bill_accounts_receivable', [
            'affiliate_id' => $affiliate->id,
            'invoice_id' => $result['extra']['invoice_id'],
            'status' => 'PENDIENTE',
            'amount_pesos' => 150000,
        ]);
    }

    // ── RF-078: PRE_CUENTA borrador ──

    public function test_pre_cuenta_can_be_regenerated(): void
    {
        $payer = Payer::query()->create([
            'razon_social' => 'Empresa Regen', 'nit_body' => '900111222',
            'digito_verificacion' => '3',
        ]);

        $cuenta = CuentaCobro::query()->create([
            'payer_id' => $payer->id, 'cuenta_number' => 'CC-2026-0010',
            'period_year' => 2026, 'period_month' => 4, 'period_cobro' => '2026-04',
            'period_servicio' => '2026-04', 'generation_mode' => 'PLENO',
            'total_eps' => 10000, 'total_afp' => 10000, 'total_arl' => 1000,
            'total_ccf' => 500, 'total_admin' => 5000, 'total_affiliation' => 2000,
            'total_1' => 28500, 'status' => 'PRE_CUENTA',
        ]);

        $response = $this->postJson("/api/cuentas-cobro/{$cuenta->id}/regenerate");
        $response->assertStatus(200);
        $response->assertJsonPath('status', 'PRE_CUENTA');
    }

    public function test_definitiva_cannot_be_regenerated(): void
    {
        $payer = Payer::query()->create([
            'razon_social' => 'Empresa Def', 'nit_body' => '900222333',
            'digito_verificacion' => '5',
        ]);

        $cuenta = CuentaCobro::query()->create([
            'payer_id' => $payer->id, 'cuenta_number' => 'CC-2026-0011',
            'period_year' => 2026, 'period_month' => 4, 'period_cobro' => '2026-04',
            'period_servicio' => '2026-04', 'generation_mode' => 'PLENO',
            'total_eps' => 10000, 'total_afp' => 10000, 'total_arl' => 1000,
            'total_ccf' => 500, 'total_admin' => 5000, 'total_affiliation' => 2000,
            'total_1' => 28500, 'status' => 'DEFINITIVA',
        ]);

        $response = $this->postJson("/api/cuentas-cobro/{$cuenta->id}/regenerate");
        $response->assertStatus(400);
    }
}
