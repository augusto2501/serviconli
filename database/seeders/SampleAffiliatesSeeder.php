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

        $personTitular2 = Person::query()->firstOrCreate(
            ['document_number' => '1029384756'],
            [
                'document_type' => 'CC',
                'first_name' => 'Laura',
                'second_name' => 'Marcela',
                'first_surname' => 'Gómez',
                'second_surname' => 'Ruiz',
                'birth_date' => '1990-07-08',
                'gender' => 'F',
                'address' => 'Carrera 15 # 98-10',
                'neighborhood' => 'Chicó',
                'city_name' => 'Bogotá',
                'department_name' => 'Cundinamarca',
                'phone1' => '3115557788',
                'cellphone' => '3115557788',
                'email' => 'laura.gomez@example.com',
            ]
        );

        $affiliate2 = Affiliate::query()->firstOrCreate(
            ['person_id' => $personTitular2->id],
            [
                'client_type' => AffiliateClientType::INDEPENDIENTE,
                'status_id' => $statusId,
                'mora_status' => 'AL_DIA',
            ]
        );

        if (! SocialSecurityProfile::query()->where('affiliate_id', $affiliate2->id)->exists()) {
            SocialSecurityProfile::query()->create([
                'affiliate_id' => $affiliate2->id,
                'eps_entity_id' => $eps->id,
                'valid_from' => now()->toDateString(),
                'valid_until' => null,
                'ibc' => 2_200_000,
            ]);
        }

        $this->command?->info(sprintf(
            'SampleAffiliatesSeeder: 2 afiliados en afl_affiliates (ids: %d, %d) y 1 beneficiario en afl_beneficiaries.',
            $affiliate->id,
            $affiliate2->id
        ));
        $this->command?->info(sprintf(
            'Detalle: titular principal person_id=%d; beneficiario TI 10123456789 asociado a affiliate_id=%d.',
            $personTitular->id,
            $affiliate->id
        ));
    }
}
