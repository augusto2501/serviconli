<?php

namespace Database\Seeders;

use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Beneficiary;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use App\Modules\RegulatoryEngine\Models\SSEntity;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración en el esquema DOCUMENTO_RECTOR (core_people + afl_affiliates + beneficiario RF-017).
 * Idempotente por document_number / person_id.
 */
class SampleAffiliatesSeeder extends Seeder
{
    public function run(): void
    {
        $statusId = AffiliateStatus::query()->where('code', 'AFILIADO')->value('id');
        if (! $statusId) {
            $this->command?->warn('Falta estado AFILIADO en cfg_affiliate_statuses. Ejecute migraciones.');

            return;
        }

        $eps = SSEntity::query()->firstOrCreate(
            ['pila_code' => 'EPS-DEMO'],
            [
                'name' => 'EPS demostración',
                'type' => 'EPS',
                'status' => 'ACTIVE',
            ]
        );

        $personTitular = Person::query()->firstOrCreate(
            ['document_number' => '52987654'],
            [
                'document_type' => 'CC',
                'first_name' => 'Carlos',
                'second_name' => 'Alberto',
                'first_surname' => 'Rodríguez',
                'second_surname' => 'Pérez',
                'birth_date' => '1985-03-15',
                'gender' => 'M',
                'address' => 'Calle 45 # 12-34',
                'neighborhood' => 'Chapinero',
                'city_name' => 'Bogotá',
                'department_name' => 'Cundinamarca',
                'phone1' => '3001234567',
                'phone2' => '6017654321',
                'cellphone' => '3001234567',
                'email' => 'carlos.rodriguez@example.com',
            ]
        );

        $affiliate = Affiliate::query()->firstOrCreate(
            ['person_id' => $personTitular->id],
            [
                'client_type' => AffiliateClientType::SERVICONLI,
                'status_id' => $statusId,
                'mora_status' => 'AL_DIA',
            ]
        );

        if (! SocialSecurityProfile::query()->where('affiliate_id', $affiliate->id)->exists()) {
            SocialSecurityProfile::query()->create([
                'affiliate_id' => $affiliate->id,
                'eps_entity_id' => $eps->id,
                'valid_from' => now()->toDateString(),
                'valid_until' => null,
                'ibc' => 3_500_000,
            ]);
        }

        Beneficiary::query()->firstOrCreate(
            [
                'affiliate_id' => $affiliate->id,
                'document_number' => '10123456789',
            ],
            [
                'document_type' => 'TI',
                'first_name' => 'María Sofía',
                'surnames' => 'Rodríguez Pérez',
                'birth_date' => '2015-08-22',
                'gender' => 'F',
                'parentesco' => 'hijo_menor',
                'eps_entity_id' => $eps->id,
                'status' => 'ACTIVO',
            ]
        );

        $this->command?->info(sprintf(
            'SampleAffiliatesSeeder: titular person_id=%d → afl_affiliates.id=%d; beneficiario en afl_beneficiaries (documento TI 10123456789).',
            $personTitular->id,
            $affiliate->id
        ));
    }
}
