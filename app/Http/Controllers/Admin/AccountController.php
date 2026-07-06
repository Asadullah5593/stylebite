<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function profile(): View
    {
        $user = auth()->user()->load([
            'profile',
            'profileBadges',
            'sessions' => fn ($query) => $query->latest('last_seen_at')->limit(5),
            'deviceTokens' => fn ($query) => $query->latest('last_used_at')->limit(5),
        ])->loadCount([
            'posts',
            'memories',
            'followers',
            'following',
        ]);

        return view('admin.account.ProfilePage', compact('user'));
    }

    public function settings(): View
    {
        $user = auth()->user()->load('profile');

        return view('admin.account.SettingsPage', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'full_name' => ['nullable', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'locale' => ['nullable', 'string', 'max:16'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'profile.display_name' => ['nullable', 'string', 'max:120'],
            'profile.headline' => ['nullable', 'string', 'max:160'],
            'profile.bio' => ['nullable', 'string', 'max:500'],
            'profile.website_url' => ['nullable', 'url', 'max:255'],
            'profile.city' => ['nullable', 'string', 'max:120'],
            'profile.country' => ['nullable', 'string', 'max:120'],
        ]);

        $user->fill([
            'full_name' => $data['full_name'] ?? null,
            'username' => $data['username'],
            'email' => $data['email'],
            'locale' => $data['locale'] ?? $user->locale ?? 'en',
            'timezone' => $data['timezone'] ?? $user->timezone ?? config('app.timezone', 'UTC'),
        ]);

        if (! empty($data['password'])) {
            $user->password_hash = Hash::make($data['password']);
        }

        $user->save();

        $profileData = $data['profile'] ?? [];

        if ($profileData !== []) {
            $profile = $user->profile ?: new Profile(['user_id' => $user->id]);
            $profile->fill($profileData);

            if (! $profile->exists) {
                $profile->user_id = $user->id;
            }

            $profile->save();
        }

        return redirect()
            ->route('admin.account.settings')
            ->with('status', 'Your account settings were updated successfully.');
    }
}
