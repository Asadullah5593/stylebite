<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Notification types the bell feed shows. 'message' rows keep being created
     * on every chat message — they drive the FCM push and its delivery logs —
     * but chat carries its own unread badge, so surfacing them here would count
     * the same signal twice.
     */
    private const HIDDEN_TYPES = ['message'];

    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->integer('page', 1);
        $user = $request->user();

        $paginator = $this->visibleNotifications($user->id)
            ->with('actor.profile')
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
            'unread_count' => $this->visibleNotifications($user->id)
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

        $updatedCount = $this->visibleNotifications($userId)
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

        // Scoped to visible types: clearing must not delete the hidden 'message'
        // rows, which double as the push-delivery audit trail.
        $deletedCount = $this->visibleNotifications($userId)->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Notifications cleared successfully.',
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * @return Builder<Notification>
     */
    private function visibleNotifications(int $userId)
    {
        return Notification::query()
            ->where('recipient_user_id', $userId)
            ->whereNotIn('type', self::HIDDEN_TYPES);
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
