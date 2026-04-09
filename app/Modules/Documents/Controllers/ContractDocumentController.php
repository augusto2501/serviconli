<?php

namespace App\Modules\Documents\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Documents\Services\ContractPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ContractDocumentController extends Controller
{
    public function show(
        Request $request,
        Affiliate $affiliate,
        string $code,
        ContractPdfService $contractPdfService,
    ): JsonResponse|StreamedResponse {
        $this->authorize('view', $affiliate);

        return $contractPdfService->download($affiliate, $code, $request);
    }
}
