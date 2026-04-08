<?php

namespace App\Modules\Communications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\Models\CommNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CommNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommNotification::class);

        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));
        $paginator = CommNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (CommNotification $n): array => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'readAt' => $n->read_at?->toIso8601String(),
                'actionUrl' => $n->action_url,
            ])->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function markRead(Request $request, CommNotification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json([
            'id' => $notification->id,
            'readAt' => $notification->read_at->toIso8601String(),
        ]);
    }
}
