<?php

namespace App\Modules\Affiliates\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Services\PaymentCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentCertificateController extends Controller
{
    public function show(
        Request $request,
        Affiliate $affiliate,
        PaymentCertificateService $certificates,
    ): JsonResponse {
        $this->authorize('view', $affiliate);

        $v = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return response()->json($certificates->forPeriod(
            $affiliate,
            (int) $v['year'],
            (int) $v['month'],
        ));
    }

    public function pdf(
        Request $request,
        Affiliate $affiliate,
        PaymentCertificateService $certificates,
    ): JsonResponse|StreamedResponse {
        $this->authorize('view', $affiliate);

        $v = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $data = $certificates->forPeriod($affiliate, (int) $v['year'], (int) $v['month']);

        if (! $data['paid']) {
            return response()->json(['message' => $data['message']], 422);
        }

        return $certificates->downloadPdf($affiliate, $data);
    }
}
