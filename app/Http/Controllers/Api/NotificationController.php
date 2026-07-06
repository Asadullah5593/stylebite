<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->integer('page', 1);
        $user = $request->user();

        $paginator = Notification::query()
            ->with('actor.profile')
            ->where('recipient_user_id', $user->id)
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        return response()->json([
            'status_code' => 1,
            'message' => 'Notifications fetched successfully.',
            'notifications' => $paginator->getCollection()
                ->map(fn (Notification $notification) => $this->notificationPayload($notification))
                ->values(),
            'pagination' => $this->paginationPayload($paginator),
            'unread_count' => Notification::query()
                ->where('recipient_user_id', $user->id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function read(Request $request, int $notificationId): JsonResponse
    {
        $notification = $this->findUserNotification($request->user()->id, $notificationId);

        if (! $notification->is_read) {
            $notification->forceFill([
                'is_read' => true,
                'read_at' => now(),
            ])->save();
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Notification marked as read successfully.',
            'notification' => $this->notificationPayload($notification->fresh(['actor.profile'])),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $updatedCount = Notification::query()
            ->where('recipient_user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'All notifications marked as read successfully.',
            'updated_count' => $updatedCount,
        ]);
    }

    public function destroy(Request $request, int $notificationId): JsonResponse
    {
        $notification = $this->findUserNotification($request->user()->id, $notificationId);

        $notification->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Notification deleted successfully.',
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $deletedCount = Notification::query()
            ->where('recipient_user_id', $userId)
            ->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Notifications cleared successfully.',
            'deleted_count' => $deletedCount,
        ]);
    }

    private function findUserNotification(int $userId, int $notificationId): Notification
    {
        $notification = Notification::query()
            ->with('actor.profile')
            ->where('recipient_user_id', $userId)
            ->whereKey($notificationId)
            ->first();

        if ($notification) {
            return $notification;
        }

        abort(response()->json([
            'status_code' => 0,
            'message' => 'Notification not found.',
        ], Response::HTTP_NOT_FOUND));
    }

    private function notificationPayload(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'entity_type' => $notification->entity_type,
            'entity_id' => $notification->entity_id,
            'title' => $notification->title,
            'body' => $notification->body,
            'image_url' => stylebite_asset_url($notification->image_url),
            'action_url' => $notification->action_url,
            'is_read' => (bool) $notification->is_read,
            'read_at' => optional($notification->read_at)?->toDateTimeString(),
            'delivery_status' => $notification->delivery_status,
            'created_at' => optional($notification->created_at)?->toDateTimeString(),
            'actor' => $notification->actor ? [
                'id' => $notification->actor->id,
                'username' => $notification->actor->username,
                'full_name' => $notification->actor->full_name,
                'display_name' => $notification->actor->profile?->display_name ?? $notification->actor->full_name,
                'avatar_url' => stylebite_asset_url($notification->actor->avatar_url),
            ] : null,
        ];
    }

    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }
}
