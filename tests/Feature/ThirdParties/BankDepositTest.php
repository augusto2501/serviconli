<?php

namespace Tests\Feature\ThirdParties;

use App\Modules\ThirdParties\Models\BankDeposit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankDepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_flags_duplicate_reference_without_blocking(): void
    {
        $first = $this->postJson('/api/third-parties/bank-deposits', [
            'bank_name' => 'Banco A',
            'reference' => 'REF-777',
            'amount_pesos' => 100_000,
            'deposit_type' => 'LOCAL',
            'expected_amount_pesos' => 90_000,
        ]);
        $first->assertCreated()->assertJsonPath('duplicateReferenceWarning', false)
            ->assertJsonPath('data.surplusPesos', 10_000);

        $second = $this->postJson('/api/third-parties/bank-deposits', [
            'bank_name' => 'Banco B',
            'reference' => 'REF-777',
            'amount_pesos' => 50_000,
            'deposit_type' => 'NACIONAL',
        ]);
        $second->assertCreated()->assertJsonPath('duplicateReferenceWarning', true);

        $this->assertSame(2, BankDeposit::query()->count());
    }
}
