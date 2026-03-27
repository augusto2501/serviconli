<?php

namespace Tests\Feature\RegulatoryEngine;

use App\Modules\RegulatoryEngine\Enums\ExceptionType;
use App\Modules\RegulatoryEngine\Models\OperationalException;
use App\Modules\RegulatoryEngine\Services\OperationalExceptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalExceptionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_active_and_effective_exceptions_for_target(): void
    {
        OperationalException::query()->create([
            'exception_type' => ExceptionType::MORA_EXEMPT->value,
            'target_type' => 'AFFILIATE',
            'target_id' => 10,
            'value' => null,
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'is_active' => true,
        ]);

        OperationalException::query()->create([
            'exception_type' => ExceptionType::MORA_RATE_OVERRIDE->value,
            'target_type' => 'AFFILIATE',
            'target_id' => 10,
            'value' => ['rate_percent' => 0.05],
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-01-15',
            'is_active' => true,
        ]);

        // Fuera de vigencia
        OperationalException::query()->create([
            'exception_type' => ExceptionType::CUSTOM_RULE->value,
            'target_type' => 'AFFILIATE',
            'target_id' => 10,
            'value' => ['rule' => 'x'],
            'valid_from' => '2025-01-01',
            'valid_until' => '2025-12-31',
            'is_active' => true,
        ]);

        // Inactiva
        OperationalException::query()->create([
            'exception_type' => ExceptionType::PAYMENT_EXTENSION->value,
            'target_type' => 'AFFILIATE',
            'target_id' => 10,
            'value' => ['days' => 5],
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'is_active' => false,
        ]);

        // Otro target
        OperationalException::query()->create([
            'exception_type' => ExceptionType::MORA_EXEMPT->value,
            'target_type' => 'PAYER',
            'target_id' => 10,
            'value' => null,
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'is_active' => true,
        ]);

        $service = new OperationalExceptionService;

        $rows = $service->activeForTarget('AFFILIATE', 10, '2026-01-10');

        $this->assertCount(2, $rows);
        $this->assertTrue($service->isMoraExempt('AFFILIATE', 10, '2026-01-10'));
        $this->assertSame(0.05, $service->moraRateOverridePercent('AFFILIATE', 10, '2026-01-10'));
    }
}
