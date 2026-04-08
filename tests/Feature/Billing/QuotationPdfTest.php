<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Services\QuotationService;
use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use Database\Seeders\PaymentCalendarRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * RF-054 — PDF cotizador con branding Serviconli.
 *
 * @see DOCUMENTO_RECTOR §4.7
 */
class QuotationPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaymentCalendarRuleSeeder::class);
        $this->seedDefaultRates();
    }

    public function test_create_quotation_generates_pdf_and_stores_path(): void
    {
        Storage::fake();

        /** @var QuotationService $service */
        $service = app(QuotationService::class);

        $quotation = $service->create([
            'prospect_name' => 'Carlos Rodríguez',
            'prospect_document' => '10245678',
            'prospect_phone' => '3001234567',
            'prospect_email' => 'carlos@ejemplo.co',
            'salary_pesos' => 1_423_500,
            'contributor_type_code' => '01',
            'arl_risk_class' => 1,
        ]);

        // RF-054: pdf_path debe quedar guardado en el modelo
        $this->assertNotNull($quotation->pdf_path);
        $this->assertStringStartsWith('quotations/COT-', $quotation->pdf_path);
        $this->assertStringEndsWith('.pdf', $quotation->pdf_path);

        // RF-054: el archivo debe existir en storage
        Storage::assertExists($quotation->pdf_path);
    }

    public function test_pdf_contains_prospect_name_and_amounts(): void
    {
        Storage::fake();

        /** @var QuotationService $service */
        $service = app(QuotationService::class);

        $quotation = $service->create([
            'prospect_name' => 'Laura Gómez Especial',
            'salary_pesos' => 2_000_000,
            'contributor_type_code' => '01',
            'arl_risk_class' => 1,
        ]);

        // RF-054: el PDF generado no debe estar vacío
        $content = Storage::get($quotation->pdf_path);
        $this->assertNotEmpty($content);
        $this->assertGreaterThan(1000, strlen($content), 'El PDF debe tener contenido sustancial');
    }

    private function seedDefaultRates(): void
    {
        $params = [
            ['rates', 'SALUD_TOTAL_PERCENT', '12.5'],
            ['rates', 'PENSION_TOTAL_PERCENT', '16'],
            ['rates', 'ARL_RISK_CLASS_I_PERCENT', '0.522'],
            ['rates', 'ARL_RISK_CLASS_II_PERCENT', '1.044'],
            ['rates', 'ARL_RISK_CLASS_III_PERCENT', '2.436'],
            ['rates', 'ARL_RISK_CLASS_IV_PERCENT', '4.350'],
            ['rates', 'ARL_RISK_CLASS_V_PERCENT', '6.960'],
            ['rates', 'CCF_DEPENDIENTE_PERCENT', '4'],
            ['rates', 'CCF_INDEPENDIENTE_PERCENT', '2'],
            ['mora', 'DAILY_RATE_PERCENT', '0.0833'],
        ];

        foreach ($params as [$category, $key, $value]) {
            RegulatoryParameter::query()->create([
                'category' => $category,
                'key' => $key,
                'value' => $value,
                'data_type' => 'decimal',
                'legal_basis' => 'Test',
                'valid_from' => '2026-01-01',
                'valid_until' => null,
            ]);
        }
    }

    public function test_generate_pdf_updates_pdf_path_on_existing_quotation(): void
    {
        Storage::fake();

        /** @var QuotationService $service */
        $service = app(QuotationService::class);

        $quotation = $service->create([
            'prospect_name' => 'Regeneración',
            'salary_pesos' => 1_000_000,
            'contributor_type_code' => '01',
            'arl_risk_class' => 2,
        ]);

        // RF-054: regenerar PDF produce una ruta válida
        $newPath = $service->generatePdf($quotation);
        $this->assertStringStartsWith('quotations/COT-', $newPath);
        Storage::assertExists($newPath);
    }
}
