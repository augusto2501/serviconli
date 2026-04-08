<?php

namespace App\Modules\Advisors\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advisors\Models\AdvisorCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdvisorCommissionController extends Controller
{
    public function update(Request $request, AdvisorCommission $advisorCommission): JsonResponse
    {
        $this->authorize('update', $advisorCommission);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['PAGADA', 'ANULADA'])],
        ]);

        if ($advisorCommission->status !== 'CALCULADA') {
            return response()->json([
                'message' => 'Solo se puede cambiar el estado desde CALCULADA.',
            ], 422);
        }

        $advisorCommission->update(['status' => $validated['status']]);

        return response()->json([
            'id' => $advisorCommission->id,
            'publicNumber' => $advisorCommission->public_number,
            'status' => $advisorCommission->status,
        ]);
    }
}
