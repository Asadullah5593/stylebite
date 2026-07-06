<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delete Account Instructions | {{ config('app.name', 'Stylebite') }}</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <main class="mx-auto max-w-3xl px-6 py-12 sm:px-8 lg:py-16">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">How to Delete Your Account</h1>
            <p class="mt-4 text-slate-700">To delete your account, follow these steps:</p>

            <ol class="mt-6 list-decimal space-y-3 pl-6 text-slate-800">
                <li>Open the app and go to your <strong>Profile</strong>.</li>
                <li>Click on the <strong>Settings</strong> icon.</li>
                <li>Scroll to the bottom of the settings page.</li>
                <li>Tap the <strong>Delete Account</strong> option.</li>
            </ol>

            <p class="mt-6 text-sm text-slate-600">
                Note: Deleting your account may permanently remove your data and cannot be undone.
            </p>
        </div>
    </main>
</body>
</html>
