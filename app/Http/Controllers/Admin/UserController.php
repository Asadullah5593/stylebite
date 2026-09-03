<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\PasswordReset;
use App\Models\Profile;
use App\Models\ProfileBadge;
use App\Models\ActivityLog;
use App\Models\StreakGraceDay;
use App\Models\User;
use App\Models\UserAuthProvider;
use App\Models\UserSetting;
use App\Models\UserSession;
use App\Services\FollowCountSynchronizer;
use App\Services\UserModerationService;
use App\Support\CsvExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = $this->filteredUsers($request)
            ->with('profile')
            ->withCount(['posts', 'memories', 'sessions', 'deviceTokens'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.AllUsersPage', compact('users'));
    }

    /**
     * The user-list query, shared by the table and the CSV export so an export
     * always matches what the admin is looking at.
     */
    private function filteredUsers(Request $request)
    {
        return User::query()
            ->when($request->boolean('with_deleted') || $request->string('status')->toString() === 'deleted', fn ($query) => $query->withTrashed())
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = $request->string('status')->toString();

                if ($status === 'deleted') {
                    $query->onlyTrashed();

                    return;
                }

                $query->where('status', $status);
            });
    }

    /**
     * Server-side CSV of the current filter.
     *
     * Replaces a client-side "Export" button that scraped the rendered table:
     * with pagination at 10, asking for "all users" produced a ten-row file, it
     * wrote no audit row because no request reached the server, and it built CSV
     * by string interpolation so any value containing a quote corrupted the file.
     */
    public function export(Request $request)
    {
        ActivityLog::record(
            eventName: 'users_exported',
            entityType: 'user',
            entityId: null,
            metadata: [
                'filters' => $request->only(['q', 'role', 'status', 'with_deleted']),
            ],
            description: 'Exported the user list as CSV',
        );

        $query = $this->filteredUsers($request)->with('profile');

        return CsvExport::stream(
            'stylebite-users-'.now()->format('Y-m-d-His').'.csv',
            ['ID', 'Username', 'Full Name', 'Email', 'Role', 'Status', 'Suspended Until', 'Status Reason',
             'Email Verified At', 'Last Login', 'Last Seen', 'City', 'Country', 'Created At', 'Deleted At'],
            fn () => $query->lazyByIdDesc(500)->map(fn (User $user) => [
                $user->id,
                $user->username,
                $user->full_name,
                $user->email,
                $user->role,
                $user->status,
                $user->suspended_until?->toDateTimeString(),
                $user->status_reason,
                $user->email_verified_at?->toDateTimeString(),
                $user->last_login_at?->toDateTimeString(),
                $user->last_seen_at?->toDateTimeString(),
                $user->profile?->city,
                $user->profile?->country,
                $user->created_at?->toDateTimeString(),
                $user->deleted_at?->toDateTimeString(),
            ])
        );
    }

    /**
     * Everything held about one person, as JSON.
     *
     * This is the access/portability request (GDPR Art. 15/20) counterpart to the
     * public delete-account page: erasure was already possible, but there was no
     * way to answer "send me my data" short of hand-querying dozens of tables.
     * JSON rather than CSV because the data is nested and must stay structured.
     */
    public function exportPersonalData(User $user)
    {
        ActivityLog::record(
            eventName: 'user_personal_data_exported',
            entityType: 'user',
            entityId: $user->id,
            metadata: ['email' => $user->email],
            description: "Exported all personal data held for user #{$user->id}",
        );

        $user->load(['profile', 'settings', 'authProviders', 'profileBadges']);

        $payload = [
            'exported_at' => now()->toISOString(),
            'exported_by_admin_id' => auth()->id(),
            'account' => $user->only([
                'id', 'username', 'email', 'full_name', 'phone_country_code', 'phone_number',
                'role', 'status', 'status_reason', 'suspended_until', 'locale', 'timezone',
                'email_verified_at', 'phone_verified_at', 'last_login_at', 'last_seen_at',
                'created_at', 'updated_at', 'deleted_at',
            ]),
            'profile' => $user->profile?->toArray(),
            'settings' => $user->settings?->toArray(),
            'linked_accounts' => $user->authProviders->map(fn ($provider) => [
                'provider' => $provider->provider,
                'provider_email' => $provider->provider_email,
                'linked_at' => $provider->created_at?->toISOString(),
            ])->values(),
            'badges' => $user->profileBadges->pluck('badge_key')->values(),
            'posts' => $user->posts()->get(['id', 'post_type', 'caption', 'status', 'visibility', 'city', 'country', 'created_at']),
            'memories' => $user->memories()->get(['id', 'title', 'created_at']),
            'comments' => $user->comments()->get(['id', 'post_id', 'body', 'status', 'created_at']),
            'comment_replies' => $user->commentReplies()->get(['id', 'comment_id', 'body', 'status', 'created_at']),
            'messages_sent' => $user->sentMessages()->get(['id', 'conversation_id', 'body', 'created_at']),
            'notifications' => $user->notifications()->get(['id', 'type', 'title', 'body', 'is_read', 'created_at']),
            'support_tickets' => $user->supportTickets()->with('visibleMessages:id,support_ticket_id,author_type,body,created_at')->get(),
            'reports_filed' => $user->reportsMade()->get(['id', 'target_type', 'target_id', 'reason', 'status', 'created_at']),
            'earnings_wallet' => $user->earningsWallet,
            'earning_transactions' => $user->earningTransactions()->get(['id', 'transaction_type', 'source_type', 'amount', 'currency_code', 'status', 'created_at']),
            'withdrawal_requests' => $user->withdrawalRequests()->get(['id', 'amount', 'currency_code', 'status', 'created_at']),
            'sessions' => $user->sessions()->get(['id', 'platform', 'device_name', 'ip_address', 'created_at', 'expires_at', 'revoked_at']),
            'devices' => $user->deviceTokens()->get(['id', 'platform', 'device_id', 'app_version', 'is_active', 'last_used_at']),
            'legal_acceptances' => \App\Models\LegalAcceptance::where('user_id', $user->id)
                ->get(['document_key', 'document_version', 'accepted_at', 'ip_address']),
            'blocks_made' => $user->blockedUsersEntries()->get(['blocked_user_id', 'reason', 'created_at']),
        ];

        $filename = 'personal-data-user-'.$user->id.'-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function create(): View
    {
        return view('admin.users.CreateUserPage', [
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function profiles(Request $request): View
    {
        $profiles = Profile::query()
            ->with('user:id,username,email,full_name')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('display_name', 'like', "%{$search}%")
                        ->orWhere('bio', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('visibility'), fn ($query) => $query->where('visibility', $request->string('visibility')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.ProfilesPage', compact('profiles'));
    }

    public function settings(Request $request): View
    {
        $settings = UserSetting::query()
            ->with('user:id,username,email,full_name')
            ->when($request->filled('q'), fn ($query) => $query->whereHas('user', fn ($query) => $query
                ->where('username', 'like', '%'.$request->string('q')->toString().'%')
                ->orWhere('email', 'like', '%'.$request->string('q')->toString().'%')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.SettingsPage', compact('settings'));
    }

    public function authProviders(Request $request): View
    {
        $providers = UserAuthProvider::query()
            ->with('user:id,username,email,full_name')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where('provider', 'like', "%{$search}%")
                    ->orWhere('provider_email', 'like', "%{$search}%")
                    ->orWhere('provider_user_id', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query->where('username', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.AuthProvidersPage', compact('providers'));
    }

    public function sessions(Request $request): View
    {
        $sessions = UserSession::query()
            ->with('user:id,username,email,full_name')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where('device_name', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query->where('username', 'like', "%{$search}%"));
            })
            ->when($request->filled('platform'), fn ($query) => $query->where('platform', $request->string('platform')))
            ->latest('last_seen_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.SessionsPage', compact('sessions'));
    }

    public function devices(Request $request): View
    {
        $devices = DeviceToken::query()
            ->with('user:id,username,email,full_name')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where('device_id', 'like', "%{$search}%")
                    ->orWhere('push_token', 'like', "%{$search}%")
                    ->orWhere('app_version', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query->where('username', 'like', "%{$search}%"));
            })
            ->when($request->filled('platform'), fn ($query) => $query->where('platform', $request->string('platform')))
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->latest('last_used_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.DevicesPage', compact('devices'));
    }

    public function passwordResets(Request $request): View
    {
        $passwordResets = PasswordReset::query()
            ->with('user:id,username,email,full_name')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where('email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query->where('username', 'like', "%{$search}%"));
            })
            ->when($request->string('status')->toString() === 'used', fn ($query) => $query->whereNotNull('used_at'))
            ->when($request->string('status')->toString() === 'pending', fn ($query) => $query->whereNull('used_at'))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.PasswordResetsPage', compact('passwordResets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['nullable', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'status' => ['required', Rule::in(['active', 'inactive', 'banned'])],
            'locale' => ['nullable', 'string', 'max:16'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $user = User::create([
            'full_name' => $data['full_name'] ?? null,
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'role' => $this->enumRoleFor($data['role']),
            'status' => $data['status'],
            'locale' => $data['locale'] ?? 'en',
            'timezone' => $data['timezone'] ?? config('app.timezone', 'UTC'),
        ]);

        $user->assignRole($data['role']);

        $this->logActivity('user_created', 'user', $user->id, [
            'email' => $user->email,
            'username' => $user->username,
            'role' => $data['role'],
            'status' => $user->status,
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $user->load([
            'profile',
            'settings',
            'authProviders',
            'sessions' => fn ($query) => $query->latest('last_seen_at'),
            'deviceTokens' => fn ($query) => $query->latest('last_used_at'),
            'profileBadges',
            'passwordResets' => fn ($query) => $query->latest('created_at')->limit(5),
            'posts' => fn ($query) => $query->latest()->limit(5),
            'memories' => fn ($query) => $query->latest()->limit(5),
        ])->loadCount([
            'posts',
            'memories',
            'followers',
            'following',
            'sessions',
            'deviceTokens',
            'reportsMade',
        ]);

        $badgeCatalog = $this->badgeCatalog();

        return view('admin.users.ShowUserPage', compact('user', 'badgeCatalog'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.EditUserPage', [
            'user' => $user,
            'roleOptions' => $this->roleOptions(),
            'currentRoleName' => $this->currentRoleName($user),
        ]);
    }

    /**
     * Spatie role names for the role picker (web guard).
     */
    private function roleOptions(): array
    {
        return \Spatie\Permission\Models\Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * The user's Spatie role, falling back to the legacy enum column for
     * accounts that predate the permission system.
     */
    private function currentRoleName(User $user): string
    {
        return $user->roles()->pluck('name')->first() ?? $user->role;
    }

    /**
     * Mirror a Spatie role onto the legacy users.role enum when it coincides
     * with an app-level account type; custom panel roles leave it untouched.
     */
    private function enumRoleFor(string $roleName, string $fallback = 'user'): string
    {
        return in_array($roleName, ['user', 'creator', 'moderator', 'admin'], true)
            ? $roleName
            : $fallback;
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $currentRoleName = $this->currentRoleName($user);

        $data = $request->validate([
            'full_name' => ['nullable', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            // Suspensions need a duration, so they only happen through the
            // dedicated suspend control — the form offers active/banned plus
            // whatever state the account is already in.
            'status' => ['required', Rule::in(array_unique(['active', 'banned', $user->status]))],
            'locale' => ['nullable', 'string', 'max:16'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        if (auth()->id() === $user->id && ($data['role'] !== $currentRoleName || $data['status'] !== $user->status)) {
            return back()->with('status', 'You cannot change your own role or account status from the edit form.');
        }

        $statusChanged = $data['status'] !== $user->status;
        $roleChanged = $data['role'] !== $currentRoleName;

        $user->fill([
            'full_name' => $data['full_name'] ?? null,
            'username' => $data['username'],
            'email' => $data['email'],
            'role' => $roleChanged ? $this->enumRoleFor($data['role'], $user->role) : $user->role,
            'locale' => $data['locale'] ?? 'en',
            'timezone' => $data['timezone'] ?? config('app.timezone', 'UTC'),
        ]);

        if (! empty($data['password'])) {
            $user->password_hash = Hash::make($data['password']);
        }

        $user->save();

        if ($roleChanged) {
            $user->syncRoles([$data['role']]);

            $this->logActivity('user_role_updated', 'user', $user->id, [
                'from' => $currentRoleName,
                'to' => $data['role'],
            ]);
        }

        // Status flips go through the moderation service so the reason log and
        // session revocation can't be skipped by using the edit form.
        if ($statusChanged) {
            $service = app(UserModerationService::class);

            match ($data['status']) {
                'banned' => $service->ban($user, $request->user(), 'Status changed via the admin edit form.'),
                'active' => $service->reinstate($user, $request->user(), 'Status changed via the admin edit form.'),
                default => null,
            };
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'User updated successfully.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $request->merge(['action' => 'suspend']);

        return $this->changeStatus($request, $user);
    }

    public function changeStatus(Request $request, User $user): RedirectResponse
    {
        $this->normalizeSuspensionInput($request);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['activate', 'suspend', 'ban'])],
            'reason' => ['required_unless:action,activate', 'nullable', 'string', 'max:500'],
            'suspended_until' => ['required_if:action,suspend', 'nullable', 'date', 'after:now'],
        ], [
            'reason.required_unless' => 'Please provide a reason for this action.',
            'suspended_until.required_if' => 'Please choose when the suspension should end.',
            'suspended_until.after' => 'The suspension end time must be in the future.',
        ]);

        if ($user->trashed()) {
            return back()->with('status', 'Restore this deleted user before applying lifecycle changes.');
        }

        if (auth()->id() === $user->id && $validated['action'] !== 'activate') {
            return back()->with('status', 'You cannot apply this lifecycle action to your own account.');
        }

        $action = $validated['action'];
        $targetStatus = match ($action) {
            'activate' => 'active',
            'ban' => 'banned',
            default => 'suspended',
        };

        // Re-suspending is allowed — it re-times the window. Everything else
        // is a no-op when the user is already in the requested state.
        if ($user->status === $targetStatus && $action !== 'suspend') {
            return back()->with('status', 'User is already in the requested state.');
        }

        $service = app(UserModerationService::class);
        $admin = $request->user();
        $reason = $validated['reason'] ?? null;

        $message = match ($action) {
            'activate' => 'User activated successfully.',
            'ban' => 'User banned successfully.',
            default => 'User suspended successfully.',
        };

        match ($action) {
            'activate' => $service->reinstate($user, $admin, $reason),
            'ban' => $service->ban($user, $admin, $reason),
            default => $service->suspend(
                $user,
                $admin,
                $reason,
                CarbonImmutable::parse($validated['suspended_until'])
            ),
        };

        return back()->with('status', $message);
    }

    public function bulkLifecycle(Request $request): RedirectResponse
    {
        $this->normalizeSuspensionInput($request);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer', 'distinct'],
            'action' => ['required', Rule::in(['activate', 'suspend', 'ban'])],
            'reason' => ['required_unless:action,activate', 'nullable', 'string', 'max:500'],
            'suspended_until' => ['required_if:action,suspend', 'nullable', 'date', 'after:now'],
        ], [
            'user_ids.required' => 'Select at least one user first.',
            'user_ids.max' => 'Bulk actions are limited to 100 users at a time.',
            'reason.required_unless' => 'Please provide a reason for this action.',
            'suspended_until.required_if' => 'Please choose when the suspension should end.',
            'suspended_until.after' => 'The suspension end time must be in the future.',
        ]);

        $action = $validated['action'];
        $targetStatus = match ($action) {
            'activate' => 'active',
            'ban' => 'banned',
            default => 'suspended',
        };

        $skipped = ['self' => 0, 'admin' => 0, 'unchanged' => 0];

        // Soft-deleted accounts fall out here on purpose: whereIn on the
        // default scope only returns live users.
        $eligible = User::query()
            ->whereIn('id', $validated['user_ids'])
            ->get()
            ->filter(function (User $user) use ($action, $targetStatus, &$skipped): bool {
                if ($user->id === auth()->id()) {
                    $skipped['self']++;

                    return false;
                }

                // Other admins can only be moderated one-by-one, never in bulk.
                if ($user->role === 'admin' && $action !== 'activate') {
                    $skipped['admin']++;

                    return false;
                }

                if ($user->status === $targetStatus && $action !== 'suspend') {
                    $skipped['unchanged']++;

                    return false;
                }

                return true;
            })
            ->values();

        $count = app(UserModerationService::class)->bulk(
            $eligible,
            $request->user(),
            $action,
            $validated['reason'] ?? null,
            $action === 'suspend' ? CarbonImmutable::parse($validated['suspended_until']) : null
        );

        $verb = match ($action) {
            'activate' => 'activated',
            'ban' => 'banned',
            default => 'suspended',
        };

        $notes = array_filter([
            $skipped['self'] ? 'your own account' : null,
            $skipped['admin'] ? $skipped['admin'].' admin account(s)' : null,
            $skipped['unchanged'] ? $skipped['unchanged'].' already in that state' : null,
        ]);

        $message = "{$count} user(s) {$verb} successfully.";

        if ($notes !== []) {
            $message .= ' Skipped: '.implode(', ', $notes).'.';
        }

        return back()->with('status', $message);
    }

    /**
     * The suspend UI offers preset lengths (posted as duration_hours) or an
     * explicit end time. Fold presets into suspended_until before validation
     * so everything downstream deals with a single field.
     */
    private function normalizeSuspensionInput(Request $request): void
    {
        // A picked date is a wall clock in the admin's timezone; a preset duration
        // is already an instant. Convert the picked one first so the preset branch
        // only fires when nothing was picked.
        stylebite_normalize_admin_datetimes($request, 'suspended_until');

        if ($request->input('action') === 'suspend'
            && ! $request->filled('suspended_until')
            && is_numeric($request->input('duration_hours'))) {
            $request->merge([
                'suspended_until' => now()
                    ->addHours(min(8760, max(1, (int) $request->input('duration_hours'))))
                    ->toDateTimeString(),
            ]);
        }
    }

    public function revokeSession(User $user, UserSession $session): RedirectResponse
    {
        abort_unless($session->user_id === $user->id, 404);

        $session->update([
            'revoked_at' => now(),
            'expires_at' => now(),
        ]);

        $this->logActivity('user_session_revoked', 'user_session', $session->id, [
            'user_id' => $user->id,
            'platform' => $session->platform,
            'device_id' => $session->device_id,
        ]);

        return back()->with('status', "Session #{$session->id} revoked successfully.");
    }

    public function toggleDevice(User $user, DeviceToken $device): RedirectResponse
    {
        abort_unless($device->user_id === $user->id, 404);

        $device->update([
            'is_active' => ! $device->is_active,
        ]);

        $this->logActivity('user_device_toggled', 'device_token', $device->id, [
            'user_id' => $user->id,
            'is_active' => $device->is_active,
            'platform' => $device->platform,
        ]);

        return back()->with('status', $device->is_active
            ? "Device #{$device->id} activated successfully."
            : "Device #{$device->id} disabled successfully.");
    }

    public function expirePasswordReset(User $user, PasswordReset $passwordReset): RedirectResponse
    {
        abort_unless($passwordReset->user_id === $user->id, 404);

        if (! $passwordReset->used_at) {
            $passwordReset->update([
                'used_at' => now(),
                'expires_at' => now(),
            ]);
        }

        $this->logActivity('password_reset_expired', 'password_reset', $passwordReset->id, [
            'user_id' => $user->id,
            'email' => $passwordReset->email,
        ]);

        return back()->with('status', "Password reset #{$passwordReset->id} expired successfully.");
    }

    /**
     * Give a user back a streak they lost.
     *
     * Rather than writing a streak number — which the next recomputation would
     * overwrite — this credits the days they actually missed. The engine then
     * arrives at the restored streak on its own and keeps doing so afterwards.
     *
     * Capped two ways: a lifetime quota of restores per user, and a maximum gap
     * that a single restore may bridge, so a user who stopped posting months ago
     * cannot be handed a months-long streak with one click.
     */
    public function restoreStreak(User $user): RedirectResponse
    {
        // Read fresh: recalculation writes the profile row directly, so a
        // relation loaded earlier in the request would already be stale.
        $profile = $user->profile()->first();

        if (! $profile) {
            return back()->with('status', 'This user has no profile to restore a streak for.');
        }

        $maxRestores = (int) stylebite_app_config('streaks.max_restores', 5);
        $maxGapDays = (int) stylebite_app_config('streaks.max_restore_gap_days', 7);

        if ((int) $profile->streak_restore_count >= $maxRestores) {
            return back()->with('status', "This user has already used all {$maxRestores} streak restores.");
        }

        if (! $profile->last_streak_day) {
            return back()->with('status', 'This user has no streak history to restore.');
        }

        $timezone = stylebite_reporting_timezone();
        $yesterday = CarbonImmutable::now($timezone)->startOfDay()->subDay();
        $gapStart = CarbonImmutable::parse($profile->last_streak_day, $timezone)->startOfDay()->addDay();

        if ($gapStart->greaterThan($yesterday)) {
            return back()->with('status', 'This streak is not broken — there is nothing to restore.');
        }

        $missingDays = $gapStart->diffInDays($yesterday) + 1;

        if ($missingDays > $maxGapDays) {
            return back()->with('status', "This streak has been broken for {$missingDays} days, which is beyond the {$maxGapDays}-day restore limit.");
        }

        DB::transaction(function () use ($user, $profile, $gapStart, $missingDays) {
            for ($offset = 0; $offset < $missingDays; $offset++) {
                StreakGraceDay::insertOrIgnore([
                    'user_id' => $user->id,
                    'grace_date' => $gapStart->addDays($offset)->toDateString(),
                    'reason' => 'Admin streak restore',
                    'granted_by_user_id' => auth()->id(),
                    'created_at' => now(),
                ]);
            }

            $profile->forceFill([
                'streak_restore_count' => (int) $profile->streak_restore_count + 1,
            ])->save();
        });

        $streak = stylebite_recalculate_streak($user->id);

        $this->logActivity('streak_restored', 'user', $user->id, [
            'days_credited' => $missingDays,
            'from' => $gapStart->toDateString(),
            'streak_after' => $streak['days'],
            'restores_used' => (int) $profile->fresh()->streak_restore_count,
        ]);

        return back()->with('status', "Streak restored — {$missingDays} day(s) credited, now at {$streak['days']} days.");
    }

    /**
     * Reset a user's streak to zero.
     *
     * Moves a boundary forward instead of zeroing a column, because the engine
     * recomputes from raw activity — a zeroed column would simply come back on
     * the next run. Every day before the boundary is ignored from here on, so
     * the user starts a fresh streak from their next qualifying day. Their
     * personal best is kept.
     */
    public function resetStreak(User $user): RedirectResponse
    {
        $profile = $user->profile()->first();

        if (! $profile) {
            return back()->with('status', 'This user has no profile to reset a streak for.');
        }

        $previous = (int) $profile->current_streak_days;

        $profile->forceFill([
            'streak_reset_at' => now(),
        ])->save();

        stylebite_recalculate_streak($user->id);

        $this->logActivity('streak_reset', 'user', $user->id, [
            'streak_before' => $previous,
        ]);

        return back()->with('status', "Streak reset to 0 (was {$previous} days).");
    }

    public function toggleVerifiedBadge(User $user): RedirectResponse
    {
        $badge = $user->profileBadges()->where('badge_key', 'verified_user')->first();

        if ($badge) {
            $badge->delete();

            $this->logActivity('verified_badge_removed', 'user', $user->id, [
                'badge_key' => 'verified_user',
            ]);

            return back()->with('status', 'Verified badge removed successfully.');
        }

        ProfileBadge::create([
            'user_id' => $user->id,
            'badge_key' => 'verified_user',
            'title' => 'Verified User',
            'icon_key' => 'verified_badge',
            'status' => 'earned',
            'sort_order' => 0,
            'earned_at' => now(),
        ]);

        $this->logActivity('verified_badge_added', 'user', $user->id, [
            'badge_key' => 'verified_user',
        ]);

        return back()->with('status', 'Verified badge added successfully.');
    }

    public function updateBadge(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'badge_key' => ['required', Rule::in(array_keys($this->badgeCatalog()))],
            'action' => ['required', Rule::in(['attach', 'remove'])],
        ]);

        $badgeKey = $validated['badge_key'];
        $action = $validated['action'];
        $badgeDefinition = $this->badgeCatalog()[$badgeKey];
        $existingBadge = $user->profileBadges()->where('badge_key', $badgeKey)->first();

        if ($action === 'remove') {
            if (! $existingBadge) {
                return back()->with('status', 'Selected badge is not assigned to this user.');
            }

            $existingBadge->delete();

            $this->logActivity('user_badge_removed', 'user', $user->id, [
                'badge_key' => $badgeKey,
                'title' => $badgeDefinition['title'],
            ]);

            return back()->with('status', $badgeDefinition['title'].' removed successfully.');
        }

        if ($existingBadge) {
            return back()->with('status', 'Selected badge is already assigned to this user.');
        }

        ProfileBadge::create([
            'user_id' => $user->id,
            'badge_key' => $badgeKey,
            'title' => $badgeDefinition['title'],
            'icon_key' => $badgeDefinition['icon_key'],
            'status' => 'earned',
            'sort_order' => $user->profileBadges()->count(),
            'earned_at' => now(),
        ]);

        $this->logActivity('user_badge_added', 'user', $user->id, [
            'badge_key' => $badgeKey,
            'title' => $badgeDefinition['title'],
        ]);

        return back()->with('status', $badgeDefinition['title'].' added successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Deleting an account is the most destructive thing in the panel, so it
        // records who did it and why alongside the account details.
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => 'Please record why this account is being deleted.',
            'reason.min' => 'Please give a meaningful reason.',
        ]);

        if ($user->trashed()) {
            return back()->with('status', 'User is already deleted.');
        }

        if (auth()->id() === $user->id) {
            return back()->withErrors(['reason' => 'You cannot delete your own account.']);
        }

        $user->forceFill(['status' => 'deleted'])->save();
        $user->delete();

        // A deleted account still has rows in user_follows, so everyone on the
        // other end of them would keep counting it. Recount them and the account
        // itself, or profiles report followers nobody can open.
        app(FollowCountSynchronizer::class)->syncWithCounterparts($user->id);

        $this->logActivity('user_deleted', 'user', $user->id, [
            'status' => 'deleted',
            'email' => $user->email,
            'role' => $user->role,
            'reason' => $validated['reason'],
        ]);

        return redirect()
            ->route('admin.users.all_users')
            ->with('status', 'User deleted successfully.');
    }

    public function restore(User $user): RedirectResponse
    {
        if (! $user->trashed()) {
            return back()->with('status', 'User is already active in the admin list.');
        }

        $user->restore();
        $user->forceFill([
            'status' => $user->status === 'deleted' ? 'active' : $user->status,
        ])->save();

        // Their follows count again, so the same people need recounting back up.
        app(FollowCountSynchronizer::class)->syncWithCounterparts($user->id);

        $this->logActivity('user_restored', 'user', $user->id, [
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
        ]);

        return back()->with('status', 'User restored successfully.');
    }

    public static function tabCounts(): array
    {
        return [
            'all_users' => User::withTrashed()->count(),
            'profiles' => Profile::count(),
            'settings' => User::whereHas('settings')->count(),
            'auth_providers' => UserAuthProvider::count(),
            'sessions' => UserSession::count(),
            'devices' => DeviceToken::count(),
            'password_resets' => PasswordReset::count(),
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

    private function badgeCatalog(): array
    {
        return [
            'verified_user' => ['title' => 'Verified User', 'icon_key' => 'verified_badge'],
            'top_creator' => ['title' => 'Top Creator', 'icon_key' => 'trophy'],
            'trendsetter' => ['title' => 'Trendsetter', 'icon_key' => 'sparkles'],
            'community_voice' => ['title' => 'Community Voice', 'icon_key' => 'chat_bubble'],
            'contest_winner' => ['title' => 'Contest Winner', 'icon_key' => 'award'],
        ];
    }
}
