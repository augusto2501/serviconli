<?php

namespace Tests\Feature\Affiliations;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use Database\Seeders\RegulatoryParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiIncomeContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_multi_income_contract_rf030(): void
    {
        $this->seed(RegulatoryParameterSeeder::class);

        $affiliate = $this->makeAffiliate('ACTIVO');

        $r = $this->postJson('/api/affiliates/'.$affiliate->id.'/multi-income-contracts', [
            'period_year' => 2026,
            'period_month' => 3,
            'income_pesos' => 5_000_000,
            'contract_description' => 'Contrato A',
        ]);

        $r->assertCreated()
            ->assertJsonPath('contract.income_pesos', 5_000_000);

        $this->assertGreaterThan(0, (int) $r->json('contract.ibc_contribution_pesos'));
        $this->assertSame((int) $r->json('consolidated_ibc_pesos'), (int) $r->json('contract.ibc_contribution_pesos'));
    }

    private function makeAffiliate(string $statusCode): Affiliate
    {
        $statusId = AffiliateStatus::query()->where('code', $statusCode)->value('id');
        $this->assertNotNull($statusId);

        $person = Person::query()->create([
            'document_type' => 'CC',
            'document_number' => (string) random_int(100_000_000, 999_999_999),
            'first_name' => 'Ind',
            'first_surname' => 'Multi',
        ]);

        return Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::INDEPENDIENTE,
            'status_id' => $statusId,
            'mora_status' => 'AL_DIA',
        ]);
    }
}
