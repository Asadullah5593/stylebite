<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ActivityLog::query()
            ->with('user:id,username,full_name')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('event_name', 'like', "%{$search}%")
                        ->orWhere('entity_type', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('actor_type'), fn ($query) => $query->where('actor_type', $request->string('actor_type')))
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->string('entity_type')))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $activityStats = [
            'total' => ActivityLog::count(),
            'admins' => ActivityLog::where('actor_type', 'admin')->count(),
            'user_lifecycle' => ActivityLog::whereIn('event_name', [
                'user_deleted',
                'user_restored',
                'user_lifecycle_updated',
            ])->count(),
            'badge_events' => ActivityLog::whereIn('event_name', [
                'verified_badge_added',
                'verified_badge_removed',
                'user_badge_added',
                'user_badge_removed',
            ])->count(),
            'today' => ActivityLog::whereDate('created_at', now()->toDateString())->count(),
        ];

        $entityTypeOptions = ActivityLog::query()
            ->select('entity_type')
            ->whereNotNull('entity_type')
            ->distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type');

        return view('admin.activity.ActivityLogsPage', compact('logs', 'activityStats', 'entityTypeOptions'));
    }
}
