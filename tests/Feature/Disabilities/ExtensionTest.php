<?php

namespace Tests\Feature\Disabilities;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Disabilities\Models\AffiliateDisability;
use App\Modules\Disabilities\Services\DisabilityDayCalculator;
use App\Modules\RegulatoryEngine\Models\DiagnosisCie10;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_extension_pushes_cumulative_days_over_180_alert(): void
    {
        $cie = DiagnosisCie10::query()->create([
            'code' => 'M54.5',
            'description' => 'Lumbago',
        ]);

        $person = Person::query()->create([
            'document_number' => '445566',
            'first_name' => 'Ana',
            'first_surname' => 'Ruiz',
            'gender' => 'F',
            'address' => 'Calle 3',
        ]);

        $affiliate = Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);

        $d = AffiliateDisability::query()->create([
            'affiliate_id' => $affiliate->id,
            'source' => 'EPS_GENERAL',
            'subtype_code' => 'EG_01',
            'diagnosis_cie10_id' => $cie->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-05-31',
        ]);

        app(DisabilityDayCalculator::class)->recalculate($d);

        $this->postJson('/api/affiliates/'.$affiliate->id.'/disabilities/'.$d->id.'/extensions', [
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
        ])->assertCreated();

        $d->refresh();
        $this->assertGreaterThan(180, (int) $d->cumulative_days);
        $this->assertTrue((bool) $d->over_180_alert);
    }
}
