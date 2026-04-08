<?php

namespace App\Modules\ThirdParties\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ThirdParties\Models\BankDeposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

final class BankDepositController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', BankDeposit::class);

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:128'],
            'reference' => ['required', 'string', 'max:64'],
            'amount_pesos' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'deposit_type' => ['required', 'string', Rule::in(['LOCAL', 'NACIONAL'])],
            'expected_amount_pesos' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $duplicateReferenceWarning = BankDeposit::query()
            ->where('reference', $validated['reference'])
            ->exists();

        $deposit = BankDeposit::query()->create([
            ...$validated,
            'created_by' => Auth::id(),
        ]);

        $surplusPesos = null;
        if (isset($validated['expected_amount_pesos'])) {
            $surplusPesos = max(0, (int) $validated['amount_pesos'] - (int) $validated['expected_amount_pesos']);
        }

        return response()->json([
            'data' => [
                'id' => $deposit->id,
                'bankName' => $deposit->bank_name,
                'reference' => $deposit->reference,
                'amountPesos' => (int) $deposit->amount_pesos,
                'depositType' => $deposit->deposit_type,
                'expectedAmountPesos' => $deposit->expected_amount_pesos !== null ? (int) $deposit->expected_amount_pesos : null,
                'surplusPesos' => $surplusPesos,
                'notes' => $deposit->notes,
            ],
            'duplicateReferenceWarning' => $duplicateReferenceWarning,
        ], 201);
    }
}
