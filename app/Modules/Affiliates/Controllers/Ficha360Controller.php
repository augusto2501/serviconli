<?php

namespace App\Modules\Affiliates\Controllers;

// RF-015 — vista consolidada backend (detalle operativo)

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Services\Ficha360ViewBuilder;
use Illuminate\Http\JsonResponse;

final class Ficha360Controller extends Controller
{
    public function show(Affiliate $affiliate, Ficha360ViewBuilder $ficha360): JsonResponse
    {
        $this->authorize('view', $affiliate);

        return response()->json($ficha360->build($affiliate));
    }
}
