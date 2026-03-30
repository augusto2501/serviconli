<?php

namespace Tests\Unit\Modules\Affiliations;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use App\Modules\Affiliations\Services\SocialSecurityProfileService;
use App\Modules\RegulatoryEngine\Models\SSEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialSecurityProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_profile_covering_date_with_latest_valid_from(): void
    {
        $eps1 = SSEntity::query()->create([
            'pila_code' => 'EPS01',
            'name' => 'EPS Uno',
            'type' => 'EPS',
            'status' => 'ACTIVE',
        ]);
        $eps2 = SSEntity::query()->create([
            'pila_code' => 'EPS02',
            'name' => 'EPS Dos',
            'type' => 'EPS',
            'status' => 'ACTIVE',
        ]);

        $person = Person::query()->create(['document_number' => '900', 'first_name' => 'X']);
        $affiliate = Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);

        SocialSecurityProfile::query()->create([
            'affiliate_id' => $affiliate->id,
            'eps_entity_id' => $eps1->id,
            'valid_from' => '2020-01-01',
            'valid_until' => '2023-12-31',
        ]);
        SocialSecurityProfile::query()->create([
            'affiliate_id' => $affiliate->id,
            'eps_entity_id' => $eps2->id,
            'valid_from' => '2024-01-01',
            'valid_until' => null,
        ]);

        $svc = new SocialSecurityProfileService;

        $old = $svc->currentForAffiliate($affiliate->id, '2022-06-15');
        $this->assertNotNull($old);
        $this->assertSame($eps1->id, $old->eps_entity_id);

        $current = $svc->currentForAffiliate($affiliate->id, '2025-01-01');
        $this->assertNotNull($current);
        $this->assertSame($eps2->id, $current->eps_entity_id);
    }

    public function test_returns_null_when_no_profile_covers_date(): void
    {
        $person = Person::query()->create(['document_number' => '901', 'first_name' => 'Y']);
        $affiliate = Affiliate::query()->create([
            'person_id' => $person->id,
            'client_type' => AffiliateClientType::SERVICONLI,
        ]);

        SocialSecurityProfile::query()->create([
            'affiliate_id' => $affiliate->id,
            'valid_from' => '2025-01-01',
            'valid_until' => '2025-12-31',
        ]);

        $svc = new SocialSecurityProfileService;
        $this->assertNull($svc->currentForAffiliate($affiliate->id, '2024-01-01'));
    }
}
