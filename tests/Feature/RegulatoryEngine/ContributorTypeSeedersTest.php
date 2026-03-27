<?php

namespace Tests\Feature\RegulatoryEngine;

use App\Modules\RegulatoryEngine\Models\ContributorType;
use App\Modules\RegulatoryEngine\Models\ContributorTypeSubsystem;
use Database\Seeders\ContributorTypeSeeder;
use Database\Seeders\ContributorTypeSubsystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributorTypeSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributor_types_and_subsystems_seeders_create_base_rows(): void
    {
        $this->seed(ContributorTypeSeeder::class);
        $this->seed(ContributorTypeSubsystemSeeder::class);

        $this->assertDatabaseHas('cfg_contributor_types', ['code' => '01']);
        $this->assertDatabaseHas('cfg_contributor_types', ['code' => '03']);
        $this->assertDatabaseHas('cfg_contributor_types', ['code' => '57']);

        $type01 = ContributorType::query()->where('code', '01')->firstOrFail();
        $type03 = ContributorType::query()->where('code', '03')->firstOrFail();

        $this->assertDatabaseHas('cfg_contributor_type_subsystems', [
            'contributor_type_id' => $type01->id,
            'subsystem' => 'CCF',
            'is_required' => true,
        ]);
        $this->assertDatabaseHas('cfg_contributor_type_subsystems', [
            'contributor_type_id' => $type03->id,
            'subsystem' => 'CCF',
            'is_required' => false,
        ]);

        $this->assertSame(
            16,
            ContributorTypeSubsystem::query()->count(),
            'Deben existir 4 subsistemas por cada 1 de los 4 tipos base.'
        );
    }
}
