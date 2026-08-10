<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets)
    {
    }

    public function index(Request $request): View
    {
        $ticketsQuery = SupportTicket::query()
            ->with(['user:id,username,full_name,email', 'assignee:id,username,full_name'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('subject', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->string('assigned')->toString() === 'me', fn ($query) => $query->where('assigned_to_user_id', auth()->id()))
            ->when($request->string('assigned')->toString() === 'none', fn ($query) => $query->whereNull('assigned_to_user_id'))
            // "Waiting on us" is the queue that actually matters day to day.
            ->when($request->boolean('needs_reply'), fn ($query) => $query
                ->where('last_reply_by', 'user')
                ->whereNotIn('status', SupportTicket::FINISHED_STATUSES));

        $tickets = $ticketsQuery
            ->orderByRaw("FIELD(priority,'urgent','high','normal','low')")
            ->latest('last_reply_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.support.TicketsPage', [
            'tickets' => $tickets,
            'stats' => [
                'needs_reply' => SupportTicket::where('last_reply_by', 'user')
                    ->whereNotIn('status', SupportTicket::FINISHED_STATUSES)->count(),
                'unassigned' => SupportTicket::whereNull('assigned_to_user_id')
                    ->whereNotIn('status', SupportTicket::FINISHED_STATUSES)->count(),
                'urgent' => SupportTicket::where('priority', 'urgent')
                    ->whereNotIn('status', SupportTicket::FINISHED_STATUSES)->count(),
                'open' => SupportTicket::whereNotIn('status', SupportTicket::FINISHED_STATUSES)->count(),
            ],
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load([
            'user:id,username,full_name,email,status',
            'assignee:id,username,full_name',
            'messages' => fn ($query) => $query->with(['author:id,username,full_name', 'attachments'])->orderBy('id'),
        ]);

        $this->tickets->markReadByStaff($ticket);

        return view('admin.support.TicketThreadPage', [
            'ticket' => $ticket,
            'staffOptions' => User::query()
                ->whereHas('roles')
                ->orderBy('full_name')
                ->get(['id', 'username', 'full_name']),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $isInternal = $request->boolean('is_internal');

        if ($ticket->status === 'closed' && ! $isInternal) {
            return back()->with('status', 'This ticket is closed. Reopen it before replying to the user.');
        }

        // Author type is 'staff' either way; is_internal is what decides whether
        // the user ever sees it.
        $this->tickets->addMessage(
            $ticket,
            $request->user(),
            'staff',
            $data['body'],
            $isInternal
        );

        return back()->with('status', $isInternal
            ? 'Internal note added — the user cannot see it.'
            : 'Reply sent to the user.');
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (array_key_exists('priority', $data) && $data['priority'] !== null && $data['priority'] !== $ticket->priority) {
            $ticket->forceFill(['priority' => $data['priority']])->save();
        }

        if (array_key_exists('assigned_to_user_id', $data)) {
            $ticket->forceFill(['assigned_to_user_id' => $data['assigned_to_user_id']])->save();
        }

        // Status last: it writes the system message, so the note reflects any
        // priority or assignment change made in the same submission.
        if (! empty($data['status'])) {
            $this->tickets->changeStatus($ticket, $data['status'], $request->user());
        }

        return back()->with('status', "Ticket {$ticket->reference} updated.");
    }

    public function claim(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket->forceFill([
            'assigned_to_user_id' => $request->user()->id,
            'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status,
        ])->save();

        return back()->with('status', "Ticket {$ticket->reference} assigned to you.");
    }

    public static function tabCounts(): array
    {
        return [
            'tickets' => SupportTicket::count(),
        ];
    }
}
