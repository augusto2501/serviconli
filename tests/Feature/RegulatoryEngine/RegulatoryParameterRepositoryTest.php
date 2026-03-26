<?php

namespace Tests\Feature\RegulatoryEngine;

use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use App\Modules\RegulatoryEngine\Repositories\RegulatoryParameterRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegulatoryParameterRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_value_effective_on_date(): void
    {
        RegulatoryParameter::query()->create([
            'category' => 'monetary',
            'key' => 'SMMLV',
            'value' => '1000000',
            'data_type' => 'integer',
            'legal_basis' => 'Test',
            'valid_from' => '2025-01-01',
            'valid_until' => '2025-12-31',
        ]);
        RegulatoryParameter::query()->create([
            'category' => 'monetary',
            'key' => 'SMMLV',
            'value' => '1750905',
            'data_type' => 'integer',
            'legal_basis' => 'Test',
            'valid_from' => '2026-01-01',
            'valid_until' => null,
        ]);

        $repo = new RegulatoryParameterRepository;

        $this->assertSame('1000000', $repo->valueAt('monetary', 'SMMLV', '2025-06-01'));
        $this->assertSame('1750905', $repo->valueAt('monetary', 'SMMLV', '2026-03-01'));
    }

    public function test_returns_null_when_no_row(): void
    {
        $repo = new RegulatoryParameterRepository;

        $this->assertNull($repo->valueAt('monetary', 'MISSING', '2026-01-01'));
    }
}
