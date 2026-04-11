<?php

namespace App\Modules\Security\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Security\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RF-109 — consulta de logs de auditoría.
 *
 * @see DOCUMENTO_RECTOR §14.2
 */
final class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('user');

        if ($request->has('auditable_type')) {
            $query->where('auditable_type', $request->input('auditable_type'));
        }

        if ($request->has('auditable_id')) {
            $query->where('auditable_id', $request->input('auditable_id'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate(30));
    }
}
