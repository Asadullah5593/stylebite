<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\CsvExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $logs = $this->filtered($request)
            ->with('user:id,username,full_name')
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.activity.ActivityLogsPage', [
            'logs' => $logs,
            'activityStats' => $this->stats(),
            'entityTypeOptions' => ActivityLog::query()
                ->whereNotNull('entity_type')
                ->distinct()
                ->orderBy('entity_type')
                ->pluck('entity_type'),
            'eventNameOptions' => ActivityLog::query()
                ->distinct()
                ->orderBy('event_name')
                ->pluck('event_name'),
            'actorOptions' => User::query()
                ->whereIn('id', ActivityLog::query()->whereNotNull('user_id')->distinct()->select('user_id'))
                ->orderBy('username')
                ->get(['id', 'username', 'full_name']),
        ]);
    }

    /**
     * Streamed CSV of the current filter selection — chunked, so exporting a
     * long trail cannot exhaust memory.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('user:id,username,full_name');

        ActivityLog::record(
            eventName: 'activity_log_exported',
            entityType: 'activity_log',
            metadata: ['filters' => array_filter($request->only($this->filterKeys()))],
            description: 'Exported the activity log',
        );

        // lazyByIdDesc walks backwards by primary key. Do NOT add an orderBy
        // here: forPageBeforeId only strips existing orders on the id column, so
        // any other sort survives and silently breaks the keyset pagination —
        // the export then repeats its first chunk and drops everything older.
        return CsvExport::stream(
            'stylebite-activity-log-'.now()->format('Y-m-d-His').'.csv',
            [
                'ID', 'When (UTC)', 'Actor', 'Actor Role', 'Actor Type', 'Event',
                'Description', 'Entity', 'Entity ID', 'Outcome', 'Status',
                'Method', 'Route', 'URL', 'IP', 'User Agent', 'Metadata',
            ],
            fn () => $query->lazyByIdDesc(500)->map(fn (ActivityLog $log) => [
                $log->id,
                $log->created_at?->toDateTimeString(),
                $log->user ? ($log->user->full_name ?: $log->user->username) : '-',
                $log->actor_role,
                $log->actor_type,
                $log->event_name,
                $log->description,
                $log->entity_type,
                $log->entity_id,
                $log->outcomeLabel(),
                $log->response_status,
                $log->http_method,
                $log->route_name,
                $log->url,
                $log->ip_address,
                $log->user_agent,
                $log->metadata_json ? json_encode($log->metadata_json, JSON_UNESCAPED_SLASHES) : null,
            ])
        );
    }

    /**
     * @return array<int, string>
     */
    private function filterKeys(): array
    {
        return ['q', 'actor_type', 'user_id', 'event_name', 'entity_type', 'method', 'outcome', 'date_from', 'date_to'];
    }

    private function filtered(Request $request): Builder
    {
        return ActivityLog::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('event_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('entity_type', 'like', "%{$search}%")
                        ->orWhere('route_name', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('actor_type'), fn ($query) => $query->where('actor_type', $request->string('actor_type')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('event_name'), fn ($query) => $query->where('event_name', $request->string('event_name')))
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->string('entity_type')))
            ->when($request->filled('method'), fn ($query) => $query->where('http_method', $request->string('method')))
            ->when($request->filled('outcome'), function ($query) use ($request) {
                $outcome = $request->string('outcome')->toString();

                // Rows written before this column existed are successes.
                $outcome === ActivityLog::OUTCOME_APPLIED
                    ? $query->where(fn ($q) => $q->whereNull('outcome')->orWhere('outcome', $outcome))
                    : $query->where('outcome', $outcome);
            })
            ->when($request->filled('date_from'), fn ($query) => $query->where('created_at', '>=', $request->date('date_from')->startOfDay()))
            ->when($request->filled('date_to'), fn ($query) => $query->where('created_at', '<=', $request->date('date_to')->endOfDay()));
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        return [
            'total' => ActivityLog::count(),
            'today' => ActivityLog::whereDate('created_at', now()->toDateString())->count(),
            'admins' => ActivityLog::where('actor_type', 'admin')->count(),
            'blocked' => ActivityLog::where('outcome', ActivityLog::OUTCOME_BLOCKED)->count(),
            'failed_logins' => ActivityLog::where('event_name', 'admin_login_failed')->count(),
        ];
    }
}
