<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\PasswordReset;
use App\Models\Profile;
use App\Models\ProfileBadge;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserAuthProvider;
use App\Models\UserSetting;
use App\Models\UserSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->boolean('with_deleted') || $request->string('status')->toString() === 'deleted', fn ($query) => $query->withTrashed())
            ->with('profile')
            ->withCount(['posts', 'memories', 'sessions', 'deviceTokens'])
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
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.AllUsersPage', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.CreateUserPage');
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
            'role' => ['required', Rule::in(['user', 'creator', 'moderator', 'admin'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'banned'])],
            'locale' => ['nullable', 'string', 'max:16'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $user = User::create([
            'full_name' => $data['full_name'] ?? null,
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'role' => $data['role'],
            'status' => $data['status'],
            'locale' => $data['locale'] ?? 'en',
            'timezone' => $data['timezone'] ?? config('app.timezone', 'UTC'),
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
        return view('admin.users.EditUserPage', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['nullable', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['user', 'creator', 'moderator', 'admin'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'banned'])],
            'locale' => ['nullable', 'string', 'max:16'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $user->fill([
            'full_name' => $data['full_name'] ?? null,
            'username' => $data['username'],
            'email' => $data['email'],
            'role' => $data['role'],
            'status' => $data['status'],
            'locale' => $data['locale'] ?? 'en',
            'timezone' => $data['timezone'] ?? config('app.timezone', 'UTC'),
        ]);

        if (! empty($data['password'])) {
            $user->password_hash = Hash::make($data['password']);
        }

        $user->save();

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'User updated successfully.');
    }

    public function suspend(User $user): RedirectResponse
    {
        return $this->changeLifecycleState($user, 'suspend');
    }

    public function changeStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['activate', 'suspend', 'ban'])],
        ]);

        return $this->changeLifecycleState($user, $validated['action']);
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

    public function destroy(User $user): RedirectResponse
    {
        if ($user->trashed()) {
            return back()->with('status', 'User is already deleted.');
        }

        $user->forceFill(['status' => 'deleted'])->save();
        $user->delete();

        $this->logActivity('user_deleted', 'user', $user->id, [
            'status' => 'deleted',
            'email' => $user->email,
            'role' => $user->role,
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

    private function changeLifecycleState(User $user, string $action): RedirectResponse
    {
        if ($user->trashed()) {
            return back()->with('status', 'Restore this deleted user before applying lifecycle changes.');
        }

        if (auth()->id() === $user->id && $action !== 'activate') {
            return back()->with('status', 'You cannot apply this lifecycle action to your own account.');
        }

        $targetStatus = match ($action) {
            'activate' => 'active',
            'ban' => 'banned',
            default => 'inactive',
        };

        if ($user->status === $targetStatus) {
            return back()->with('status', 'User is already in the requested state.');
        }

        $oldStatus = $user->status;

        $user->update(['status' => $targetStatus]);

        $this->logActivity('user_lifecycle_updated', 'user', $user->id, [
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $targetStatus,
            'email' => $user->email,
            'role' => $user->role,
        ]);

        $message = match ($action) {
            'activate' => 'User activated successfully.',
            'ban' => 'User banned successfully.',
            default => 'User suspended successfully.',
        };

        return back()->with('status', $message);
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
