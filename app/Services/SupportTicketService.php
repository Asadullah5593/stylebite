<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single write-path for support tickets, shared by the mobile API and the admin
 * panel, so the bookkeeping that keeps the queue honest — reply counters, who
 * spoke last, status transitions, notifications — can never drift between them.
 */
class SupportTicketService
{
    public const MAX_ATTACHMENTS = 5;

    public function create(User $user, array $data, array $files = []): SupportTicket
    {
        $ticket = DB::transaction(function () use ($user, $data, $files) {
            $ticket = SupportTicket::create([
                // Placeholder: the real reference needs the id, which only
                // exists after insert.
                'reference' => 'TK-PENDING-'.Str::random(8),
                'user_id' => $user->id,
                'category' => $data['category'],
                'subject' => $data['subject'],
                'status' => 'open',
                'priority' => 'normal',
                'last_reply_by' => 'user',
                'last_reply_at' => now(),
                'app_version' => $data['app_version'] ?? null,
                'platform' => $data['platform'] ?? null,
                'device_model' => $data['device_model'] ?? null,
                'os_version' => $data['os_version'] ?? null,
            ]);

            $ticket->forceFill(['reference' => SupportTicket::referenceFor($ticket->id)])->save();

            $this->addMessage($ticket, $user, 'user', $data['body'], false, $files);

            return $ticket;
        });

        return $ticket->fresh();
    }

    /**
     * Append a message. $authorType decides visibility and queue behaviour:
     * a staff reply moves the ticket to waiting_on_user and notifies the owner;
     * an internal note changes neither.
     */
    public function addMessage(
        SupportTicket $ticket,
        ?User $author,
        string $authorType,
        string $body,
        bool $isInternal = false,
        array $files = []
    ): SupportTicketMessage {
        $message = DB::transaction(function () use ($ticket, $author, $authorType, $body, $isInternal, $files) {
            $message = SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'author_user_id' => $author?->id,
                'author_type' => $authorType,
                'body' => $body,
                'is_internal' => $isInternal,
                // The author has obviously read their own message.
                'read_by_user_at' => $authorType === 'user' ? now() : null,
                'read_by_staff_at' => in_array($authorType, ['staff', 'system'], true) ? now() : null,
                'created_at' => now(),
            ]);

            $this->storeAttachments($ticket, $message, $files);

            // An internal note is a private annotation: it must not touch the
            // reply counters or the ticket's state, or the user would see the
            // ticket "move" for no visible reason.
            if (! $isInternal) {
                $updates = [
                    'messages_count' => $ticket->messages()->where('is_internal', false)->count(),
                    'last_reply_at' => now(),
                ];

                if ($authorType === 'user') {
                    $updates['last_reply_by'] = 'user';

                    // A reply reopens a resolved ticket — the problem evidently
                    // was not solved. A closed ticket stays closed.
                    if ($ticket->status === 'resolved') {
                        $updates['status'] = 'open';
                        $updates['resolved_at'] = null;
                    }
                } elseif ($authorType === 'staff') {
                    $updates['last_reply_by'] = 'staff';

                    if (in_array($ticket->status, ['open', 'in_progress'], true)) {
                        $updates['status'] = 'waiting_on_user';
                    }
                }

                $ticket->forceFill($updates)->save();
            }

            return $message;
        });

        if ($authorType === 'staff' && ! $isInternal) {
            $this->notifyOwner(
                $ticket->fresh(),
                'Support replied to '.$ticket->reference,
                Str::limit(strip_tags($body), 140)
            );
        }

        return $message;
    }

    /**
     * Status change with the side effects that belong to it, plus a system
     * message so the thread explains itself rather than silently jumping state.
     */
    public function changeStatus(SupportTicket $ticket, string $status, ?User $actor = null): void
    {
        if ($ticket->status === $status) {
            return;
        }

        $previous = $ticket->status;

        $ticket->forceFill([
            'status' => $status,
            'resolved_at' => $status === 'resolved' ? now() : ($status === 'closed' ? $ticket->resolved_at : null),
            'closed_at' => $status === 'closed' ? now() : null,
        ])->save();

        $this->addMessage(
            $ticket,
            $actor,
            'system',
            'Status changed from '.($ticket::STATUSES[$previous] ?? $previous).' to '.($ticket::STATUSES[$status] ?? $status).'.',
            true
        );

        if ($status === 'resolved') {
            $this->notifyOwner(
                $ticket->fresh(),
                $ticket->reference.' was resolved',
                'Reply on the ticket if you still need help.'
            );
        }
    }

    public function markReadByUser(SupportTicket $ticket): void
    {
        $ticket->messages()
            ->where('is_internal', false)
            ->whereNull('read_by_user_at')
            ->update(['read_by_user_at' => now()]);
    }

    public function markReadByStaff(SupportTicket $ticket): void
    {
        $ticket->messages()
            ->whereNull('read_by_staff_at')
            ->update(['read_by_staff_at' => now()]);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function storeAttachments(SupportTicket $ticket, SupportTicketMessage $message, array $files): void
    {
        foreach (array_slice($files, 0, self::MAX_ATTACHMENTS) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $stored = stylebite_upload_file($file, 'support/'.$ticket->id);

            SupportTicketAttachment::create([
                'support_ticket_message_id' => $message->id,
                'file_path' => $stored['file_path'],
                'original_file_name' => $stored['original_file_name'],
                'mime_type' => $stored['mime_type'],
                'size_bytes' => $stored['size_bytes'],
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Notifications go through the normal pipeline, so they land in-app and as a
     * push using the user's existing preferences and devices.
     */
    private function notifyOwner(SupportTicket $ticket, string $title, string $body): void
    {
        stylebite_notify_user(
            (int) $ticket->user_id,
            null,
            'support',
            'support_ticket',
            (int) $ticket->id,
            $title,
            $body,
            null,
            null
        );
    }
}
