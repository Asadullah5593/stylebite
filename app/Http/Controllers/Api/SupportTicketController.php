<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\SupportTicketService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * User-facing support tickets. Bug reports live here rather than in the report
 * queue, because a bug needs a back-and-forth conversation plus device details.
 *
 * Every route resolves the ticket through the signed-in user's own tickets, so
 * there is no path on which someone can read another person's ticket, and
 * internal staff notes are excluded by using visibleMessages() throughout.
 */
class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets)
    {
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'status_code' => 1,
            'message' => 'Support options fetched successfully.',
            'categories' => collect(SupportTicket::CATEGORIES)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'statuses' => collect(SupportTicket::STATUSES)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'max_attachments' => SupportTicketService::MAX_ATTACHMENTS,
            'subject_max_length' => 191,
            'body_max_length' => 5000,
        ]);
    }

    /**
     * @requestMediaType multipart/form-data
     */
    #[BodyParameter('category', description: 'One of: bug, payment, account, content_appeal, other.', required: true, type: 'string', example: 'bug')]
    #[BodyParameter('subject', required: true, type: 'string', example: 'App crashes when opening reels')]
    #[BodyParameter('body', required: true, type: 'string', example: 'It closes immediately after the first reel plays.')]
    #[BodyParameter('attachments', description: 'Up to 5 screenshots (jpg, png, webp, max 5MB each).', type: 'string', format: 'binary')]
    #[BodyParameter('app_version', type: 'string', example: '1.4.2')]
    #[BodyParameter('platform', description: 'One of: ios, android, web, desktop.', type: 'string', example: 'android')]
    #[BodyParameter('device_model', type: 'string', example: 'Pixel 7')]
    #[BodyParameter('os_version', type: 'string', example: 'Android 14')]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'subject' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:'.SupportTicketService::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'platform' => ['nullable', 'string', 'in:ios,android,web,desktop'],
            'device_model' => ['nullable', 'string', 'max:120'],
            'os_version' => ['nullable', 'string', 'max:32'],
        ], [
            'category.in' => 'Please choose a valid category.',
            'attachments.max' => 'You can attach up to '.SupportTicketService::MAX_ATTACHMENTS.' files.',
            'attachments.*.image' => 'Attachments must be images.',
            'attachments.*.max' => 'Each attachment must be under 5MB.',
        ]);

        // A cap on open tickets stops one account flooding the queue.
        $openCount = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->whereNotIn('status', SupportTicket::FINISHED_STATUSES)
            ->count();

        if ($openCount >= 5) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You already have 5 open tickets. Please continue on one of those instead.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $ticket = $this->tickets->create(
            $request->user(),
            $validated,
            $request->file('attachments', [])
        );

        return response()->json([
            'status_code' => 1,
            'message' => 'Ticket created. Our team will get back to you.',
            'ticket' => $this->ticketPayload($ticket->load('visibleMessages.attachments')),
        ], Response::HTTP_CREATED);
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->withCount(['messages as unread_count' => fn ($query) => $query
                ->where('is_internal', false)
                ->where('author_type', '!=', 'user')
                ->whereNull('read_by_user_at')])
            ->latest('last_reply_at')
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'status_code' => 1,
            'message' => 'Tickets fetched successfully.',
            'tickets' => $tickets->getCollection()->map(fn (SupportTicket $ticket) => [
                'id' => $ticket->id,
                'reference' => $ticket->reference,
                'category' => $ticket->category,
                'category_label' => $ticket->categoryLabel(),
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'status_label' => $ticket->statusLabel(),
                'messages_count' => $ticket->messages_count,
                'unread_count' => (int) $ticket->unread_count,
                'last_reply_by' => $ticket->last_reply_by,
                'last_reply_at' => $ticket->last_reply_at?->toISOString(),
                'created_at' => $ticket->created_at?->toISOString(),
            ])->values(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
                'last_page' => $tickets->lastPage(),
                'has_more_pages' => $tickets->hasMorePages(),
            ],
        ]);
    }

    public function show(Request $request, int $ticketId): JsonResponse
    {
        $ticket = $this->ownTicket($request, $ticketId);

        $this->tickets->markReadByUser($ticket);

        return response()->json([
            'status_code' => 1,
            'message' => 'Ticket fetched successfully.',
            'ticket' => $this->ticketPayload($ticket->load('visibleMessages.attachments')),
        ]);
    }

    /**
     * @requestMediaType multipart/form-data
     */
    #[BodyParameter('body', required: true, type: 'string', example: 'Still happening on the latest build.')]
    #[BodyParameter('attachments', description: 'Up to 5 screenshots (jpg, png, webp, max 5MB each).', type: 'string', format: 'binary')]
    public function reply(Request $request, int $ticketId): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:'.SupportTicketService::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $ticket = $this->ownTicket($request, $ticketId);

        if ($ticket->status === 'closed') {
            return response()->json([
                'status_code' => 0,
                'message' => 'This ticket is closed. Please open a new one.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->tickets->addMessage(
            $ticket,
            $request->user(),
            'user',
            $validated['body'],
            false,
            $request->file('attachments', [])
        );

        return response()->json([
            'status_code' => 1,
            'message' => 'Reply sent.',
            'ticket' => $this->ticketPayload($ticket->fresh()->load('visibleMessages.attachments')),
        ]);
    }

    public function close(Request $request, int $ticketId): JsonResponse
    {
        $ticket = $this->ownTicket($request, $ticketId);

        if ($ticket->status === 'closed') {
            return response()->json([
                'status_code' => 1,
                'message' => 'This ticket is already closed.',
                'ticket' => $this->ticketPayload($ticket->load('visibleMessages.attachments')),
            ]);
        }

        $this->tickets->changeStatus($ticket, 'closed', $request->user());

        return response()->json([
            'status_code' => 1,
            'message' => 'Ticket closed.',
            'ticket' => $this->ticketPayload($ticket->fresh()->load('visibleMessages.attachments')),
        ]);
    }

    /**
     * Scoping every lookup to the signed-in user's tickets means an id from
     * someone else's account is indistinguishable from one that never existed.
     */
    private function ownTicket(Request $request, int $ticketId): SupportTicket
    {
        return SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($ticketId)
            ->firstOrFail();
    }

    private function ticketPayload(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'reference' => $ticket->reference,
            'category' => $ticket->category,
            'category_label' => $ticket->categoryLabel(),
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'status_label' => $ticket->statusLabel(),
            'messages_count' => $ticket->messages_count,
            'last_reply_by' => $ticket->last_reply_by,
            'last_reply_at' => $ticket->last_reply_at?->toISOString(),
            'created_at' => $ticket->created_at?->toISOString(),
            'messages' => $ticket->visibleMessages
                ->sortBy('id')
                ->map(fn (SupportTicketMessage $message) => [
                    'id' => $message->id,
                    // 'staff' rather than a name: support identity stays generic.
                    'author_type' => $message->author_type,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toISOString(),
                    'attachments' => $message->attachments->map(fn ($attachment) => [
                        'id' => $attachment->id,
                        'url' => stylebite_asset_url($attachment->file_path),
                        'file_name' => $attachment->original_file_name,
                        'mime_type' => $attachment->mime_type,
                        'size_bytes' => $attachment->size_bytes,
                    ])->values(),
                ])->values(),
        ];
    }
}
