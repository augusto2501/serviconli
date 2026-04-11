<?php

namespace App\Modules\Security\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Security\Services\DashboardService;
use App\Modules\Security\Services\OperationalReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard gerencial + reportes operativos — RF-114, RF-115.
 *
 * @see DOCUMENTO_RECTOR §15
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly OperationalReportService $reportService,
    ) {}

    /** RF-114: GET /api/dashboard */
    public function index(): JsonResponse
    {
        return response()->json($this->dashboardService->build());
    }

    /** RF-115: GET /api/reports/daily-contributions?date=YYYY-MM-DD */
    public function dailyContributions(Request $request): JsonResponse
    {
        $date = $this->resolveDate($request);

        return response()->json($this->reportService->dailyContributions($date));
    }

    /** RF-115: GET /api/reports/mora */
    public function mora(): JsonResponse
    {
        return response()->json($this->reportService->moraReport());
    }

    /** RF-115: GET /api/reports/affiliates-by-advisor */
    public function affiliatesByAdvisor(): JsonResponse
    {
        return response()->json($this->reportService->affiliatesByAdvisor());
    }

    /** RF-115: GET /api/reports/affiliates-by-employer */
    public function affiliatesByEmployer(): JsonResponse
    {
        return response()->json($this->reportService->activeAffiliatesByEmployer());
    }

    /** RF-115: GET /api/reports/cash-reconciliation?date=YYYY-MM-DD */
    public function cashReconciliation(Request $request): JsonResponse
    {
        $date = $this->resolveDate($request);

        return response()->json($this->reportService->cashReconciliation($date));
    }

    /** RF-115: GET /api/reports/end-of-day?date=YYYY-MM-DD */
    public function endOfDay(Request $request): JsonResponse
    {
        $date = $this->resolveDate($request);

        return response()->json($this->reportService->endOfDayReport($date));
    }

    private function resolveDate(Request $request): Carbon
    {
        $raw = $request->query('date');

        return $raw ? Carbon::parse($raw) : Carbon::today();
    }
}
