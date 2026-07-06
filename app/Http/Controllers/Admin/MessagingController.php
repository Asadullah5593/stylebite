<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageRead;
use App\Models\MediaUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class MessagingController extends Controller
{
    public function conversations(Request $request): View
    {
        $conversations = Conversation::query()
            ->with([
                'creator:id,username,full_name',
                'lastMessage:id,conversation_id,sender_user_id,body,message_type,sent_at,is_deleted',
                'lastMessage.sender:id,username,full_name',
                'members.user:id,username,full_name',
            ])
            ->withCount(['members', 'messages'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhereHas('creator', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('members.user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->latest('last_message_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.messaging.ConversationsPage', compact('conversations'));
    }

    public function messages(Request $request): View
    {
        $messages = Message::query()
            ->with([
                'conversation:id,title,type',
                'sender:id,username,full_name',
                'replyToMessage:id,body',
                'attachments:id,message_id,media_type,file_url,thumbnail_url,mime_type,size_bytes',
            ])
            ->withCount(['attachments', 'reads'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('body', 'like', "%{$search}%")
                        ->orWhere('message_type', 'like', "%{$search}%")
                        ->orWhereHas('sender', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('conversation', fn ($query) => $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('type', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('message_type'), fn ($query) => $query->where('message_type', $request->string('message_type')))
            ->latest('sent_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.messaging.MessagesPage', compact('messages'));
    }

    public function members(Request $request): View
    {
        $members = ConversationMember::query()
            ->with([
                'conversation:id,title,type,messaging_stopped_at',
                'user:id,username,full_name,avatar_url',
                'lastReadMessage:id,body,message_type',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('role', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('conversation', fn ($query) => $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('type', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('joined_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.messaging.ConversationMembersPage', compact('members'));
    }

    public function attachments(Request $request): View
    {
        $attachments = MessageAttachment::query()
            ->with([
                'message:id,conversation_id,sender_user_id,body,message_type,sent_at',
                'message.sender:id,username,full_name',
                'message.conversation:id,title,type',
                'upload:id,storage_disk,storage_path,upload_status',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('media_type', 'like', "%{$search}%")
                        ->orWhere('mime_type', 'like', "%{$search}%")
                        ->orWhere('file_url', 'like', "%{$search}%")
                        ->orWhereHas('message', fn ($query) => $query
                            ->where('body', 'like', "%{$search}%")
                            ->orWhere('message_type', 'like', "%{$search}%"))
                        ->orWhereHas('message.sender', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('media_type'), fn ($query) => $query->where('media_type', $request->string('media_type')))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.messaging.AttachmentsPage', compact('attachments'));
    }

    public function reads(Request $request): View
    {
        $reads = MessageRead::query()
            ->with([
                'message:id,conversation_id,sender_user_id,body,message_type,sent_at',
                'message.sender:id,username,full_name',
                'message.conversation:id,title,type',
                'user:id,username,full_name,avatar_url',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->orWhereHas('message', fn ($query) => $query
                        ->where('body', 'like', "%{$search}%")
                        ->orWhere('message_type', 'like', "%{$search}%"))
                        ->orWhereHas('message.conversation', fn ($query) => $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('type', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->latest('read_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.messaging.ReadReceiptsPage', compact('reads'));
    }

    public function updateConversation(Request $request, Conversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:stop,resume'],
        ]);

        if ($validated['action'] === 'stop') {
            $conversation->messaging_stopped_by_user_id = auth()->id();
            $conversation->messaging_stopped_at = now();
        } else {
            $conversation->messaging_stopped_by_user_id = null;
            $conversation->messaging_stopped_at = null;
        }

        $conversation->save();

        $this->logActivity('conversation_safety_updated', 'conversation', $conversation->id, [
            'action' => $validated['action'],
            'messaging_stopped_at' => $conversation->messaging_stopped_at?->toDateTimeString(),
        ]);

        return back()->with('status', 'Conversation safety state updated.');
    }

    public function updateMessage(Request $request, Message $message): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:delete,restore'],
        ]);

        if ($validated['action'] === 'delete') {
            $message->is_deleted = true;
            $message->deleted_at = now();
        } else {
            $message->is_deleted = false;
            $message->deleted_at = null;
        }

        $message->save();

        $this->logActivity('message_safety_updated', 'message', $message->id, [
            'action' => $validated['action'],
            'conversation_id' => $message->conversation_id,
            'sender_user_id' => $message->sender_user_id,
        ]);

        return back()->with('status', 'Message safety state updated.');
    }

    public function updateAttachment(Request $request, MessageAttachment $attachment): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:mark_failed,mark_ready'],
        ]);

        if (! $attachment->upload) {
            return back()->with('status', 'Attachment is not linked to a managed upload record.');
        }

        $attachment->upload->upload_status = $validated['action'] === 'mark_failed' ? 'failed' : 'ready';
        $attachment->upload->failure_reason = $validated['action'] === 'mark_failed'
            ? 'Flagged by admin from messaging attachment review.'
            : null;
        $attachment->upload->save();

        $this->logActivity('message_attachment_safety_updated', 'message_attachment', $attachment->id, [
            'action' => $validated['action'],
            'upload_id' => $attachment->upload_id,
            'message_id' => $attachment->message_id,
            'upload_status' => $attachment->upload->upload_status,
        ]);

        return back()->with('status', 'Attachment safety state updated.');
    }

    public function exportConversation(Conversation $conversation): StreamedResponse
    {
        $conversation->load([
            'creator:id,username,full_name',
            'members.user:id,username,full_name',
            'messages.sender:id,username,full_name',
            'messages.attachments:id,message_id,file_url,media_type,mime_type',
        ]);

        $filename = 'conversation-'.$conversation->id.'-export-'.now()->format('Ymd-His').'.txt';

        $this->logActivity('conversation_exported', 'conversation', $conversation->id, [
            'member_count' => $conversation->members->count(),
            'message_count' => $conversation->messages->count(),
        ]);

        return response()->streamDownload(function () use ($conversation) {
            echo 'Conversation Export'.PHP_EOL;
            echo 'ID: '.$conversation->id.PHP_EOL;
            echo 'Title: '.($conversation->title ?: 'Conversation #'.$conversation->id).PHP_EOL;
            echo 'Type: '.$conversation->type.PHP_EOL;
            echo 'Creator: '.($conversation->creator?->full_name ?: '@'.($conversation->creator?->username ?? 'unknown')).PHP_EOL;
            echo 'Exported At: '.now()->toDateTimeString().PHP_EOL.PHP_EOL;

            echo 'Members'.PHP_EOL;
            echo str_repeat('=', 40).PHP_EOL;

            foreach ($conversation->members as $member) {
                $name = $member->user?->full_name ?: '@'.($member->user?->username ?? 'unknown');
                echo '- '.$name.' ['.$member->role.' / '.$member->status.']'.PHP_EOL;
            }

            echo PHP_EOL.'Messages'.PHP_EOL;
            echo str_repeat('=', 40).PHP_EOL;

            foreach ($conversation->messages->sortBy('sent_at') as $message) {
                $sender = $message->sender?->full_name ?: '@'.($message->sender?->username ?? 'unknown');
                echo '['.($message->sent_at?->format('Y-m-d H:i:s') ?? 'unknown time').'] '.$sender.PHP_EOL;
                echo 'Type: '.$message->message_type.($message->is_deleted ? ' | deleted' : '').PHP_EOL;
                echo 'Body: '.($message->body ?: '[no body]').PHP_EOL;

                if ($message->attachments->isNotEmpty()) {
                    echo 'Attachments:'.PHP_EOL;

                    foreach ($message->attachments as $attachment) {
                        echo '  - '.$attachment->media_type.' | '.$attachment->mime_type.' | '.$attachment->file_url.PHP_EOL;
                    }
                }

                echo str_repeat('-', 40).PHP_EOL;
            }
        }, $filename, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public static function tabCounts(): array
    {
        return [
            'conversations' => Conversation::count(),
            'members' => ConversationMember::count(),
            'messages' => Message::count(),
            'attachments' => MessageAttachment::count(),
            'reads' => MessageRead::count(),
        ];
    }

    private function logActivity(string $eventName, ?string $entityType, ?int $entityId, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'actor_type' => 'admin',
            'event_name' => $eventName,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata_json' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
