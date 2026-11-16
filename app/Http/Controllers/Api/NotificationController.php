<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->notifications->forUser($request->user()));
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $this->notifications->markRead($request->user(), $id);

        return response()->json(['message' => 'Notificação marcada como lida.']);
    }
}
