@extends('admin.layouts.app')

@section('content')
<div class="notifications-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Notifications</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Notifications</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Notifications</h1>
        <p class="text-muted small mb-0">In-app notifications with actor, recipient, and delivery context</p>
    </div>

    @include('admin.notifications.partials.tabs')

    <div class="glass rounded-4 p-4 border-white-05 mb-4">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="h5 fw-bold mb-1">Push notification sender</h2>
                <p class="text-muted small mb-0">Pick an audience, check the recipient count, then queue the campaign. Delivery runs in the background — track it on the Campaigns tab.</p>
            </div>
            <span class="badge bg-info-soft text-info rounded-pill px-3 py-2">Operations</span>
        </div>

        <form method="POST" action="{{ route('admin.notifications.announcements.send') }}" class="row g-3" id="campaignForm">
            @csrf
            <div class="col-lg-4">
                <label class="form-label small text-uppercase text-muted fw-bold">Audience</label>
                <select name="audience_type" class="form-select border-0 bg-dark-soft rounded-3" id="audienceType" required>
                    @foreach ([
                        'all_active' => 'All active users',
                        'city' => 'By city',
                        'active_posters' => 'Active posters',
                        'creator_role' => 'Creator accounts',
                        'specific' => 'Specific users',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('audience_type', 'all_active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-text text-muted extra-small">University targeting is not available yet.</div>
            </div>

            <div class="col-lg-5 audience-option" data-audience="city" hidden>
                <label class="form-label small text-uppercase text-muted fw-bold">Cities</label>
                @include('admin.partials.checkbox-picker', [
                    'name' => 'cities[]',
                    'options' => $cityOptions->map(fn ($city) => ['value' => $city, 'label' => $city])->all(),
                    'selected' => (array) old('cities', []),
                    'placeholder' => 'Search cities…',
                    'emptyText' => 'No user has set a city yet, so this audience would be empty.',
                    'note' => 'Only cities users have actually entered are listed.',
                ])
            </div>

            <div class="col-lg-4 audience-option" data-audience="active_posters" hidden>
                <label class="form-label small text-uppercase text-muted fw-bold">Posted within (days)</label>
                <input type="number" name="poster_days" value="{{ old('poster_days', 30) }}" min="1" max="365" class="form-control border-0 bg-dark-soft rounded-3">
                <div class="form-text text-muted extra-small">Users who published a post in this window.</div>
            </div>

            <div class="col-lg-5 audience-option" data-audience="specific" hidden>
                <label class="form-label small text-uppercase text-muted fw-bold">Users</label>
                @include('admin.partials.checkbox-picker', [
                    'name' => 'user_ids[]',
                    'options' => $recipientOptions->map(fn ($user) => [
                        'value' => $user->id,
                        // Avoid the "000@yopmail.com - 000@yopmail.com" duplication
                        // that appears when a display name is itself an email.
                        'label' => filled($user->full_name) && $user->full_name !== $user->email
                            ? $user->full_name
                            : '@'.$user->username,
                        'sublabel' => $user->email,
                    ])->all(),
                    'selected' => (array) old('user_ids', []),
                    'placeholder' => 'Search by name, username or email…',
                    'emptyText' => 'No active users to choose from.',
                    'note' => $recipientTotal > $recipientOptions->count()
                        ? 'Showing the '.number_format($recipientOptions->count()).' most recent of '.number_format($recipientTotal).' active users. Search filters this list only.'
                        : null,
                ])
            </div>

            <div class="col-lg-4">
                <label class="form-label small text-uppercase text-muted fw-bold">Action URL</label>
                <input type="text" name="action_url" value="{{ old('action_url') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Optional deep link or web URL">
            </div>

            <div class="col-lg-6">
                <label class="form-label small text-uppercase text-muted fw-bold">Title</label>
                <input type="text" name="title" value="{{ old('title') }}" class="form-control border-0 bg-dark-soft rounded-3" maxlength="191" placeholder="Announcement title" required>
            </div>

            <div class="col-12">
                <label class="form-label small text-uppercase text-muted fw-bold">Message</label>
                <textarea name="body" rows="4" class="form-control border-0 bg-dark-soft rounded-3" maxlength="500" placeholder="Write the notification message shown to the user..." required>{{ old('body') }}</textarea>
            </div>

            <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-3 pt-2">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <button class="btn btn-outline-dynamic rounded-3" type="button" id="previewAudienceButton">
                        <i class="bi bi-people me-2"></i>Check recipients
                    </button>
                    <span class="text-muted small" id="audiencePreviewResult"></span>
                </div>
                <button class="btn btn-primary rounded-3 px-4" type="submit">
                    <i class="bi bi-send me-2"></i>Queue Campaign
                </button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const typeSelect = document.getElementById('audienceType');
        const options = document.querySelectorAll('.audience-option');
        const previewButton = document.getElementById('previewAudienceButton');
        const previewResult = document.getElementById('audiencePreviewResult');
        const form = document.getElementById('campaignForm');

        function syncAudienceOptions() {
            options.forEach(function (option) {
                const matches = option.dataset.audience === typeSelect.value;
                option.hidden = !matches;
                option.querySelectorAll('select, input').forEach(function (field) {
                    field.disabled = !matches;
                });
            });
            previewResult.textContent = '';
        }

        typeSelect.addEventListener('change', syncAudienceOptions);
        syncAudienceOptions();

        previewButton.addEventListener('click', function () {
            previewResult.textContent = 'Checking…';

            const body = new FormData(form);

            fetch('{{ route('admin.notifications.audience.preview') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: body,
            })
                .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
                .then(function (result) {
                    if (!result.ok) {
                        previewResult.textContent = result.data.message || 'Could not work out that audience.';
                        return;
                    }
                    previewResult.textContent = result.data.count + ' recipient(s) — ' + result.data.label;
                })
                .catch(function () { previewResult.textContent = 'Could not reach the server.'; });
        });
    });
    </script>

    <form method="GET" action="{{ route('admin.notifications.notifications') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search title, body, recipient, actor...">
        </div>

        <select name="type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Types</option>
            @foreach (['vibe_request' => 'Vibe Request', 'like' => 'Like', 'comment' => 'Comment', 'reply' => 'Reply', 'follow' => 'Follow', 'contest' => 'Contest', 'message' => 'Message', 'system' => 'System'] as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="delivery_status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Delivery</option>
            @foreach (['pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped'] as $value => $label)
                <option value="{{ $value }}" @selected(request('delivery_status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.notifications.notifications') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Notification</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Recipient</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Actor</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Type</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Delivery</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notifications as $notification)
                        <tr class="border-white-05">
                            <td class="ps-4" style="min-width: 300px;">
                                <div class="small fw-semibold">{{ $notification->title ?: 'Untitled notification' }}</div>
                                <div class="text-muted extra-small">{{ str($notification->body ?: 'No body')->limit(80) }}</div>
                            </td>
                            <td>
                                <a href="{{ route('admin.users.show', $notification->recipient) }}" class="text-decoration-none">
                                    <div class="small fw-semibold">{{ $notification->recipient?->full_name ?: ($notification->recipient?->username ? '@'.$notification->recipient->username : 'Removed account') }}</div>
                                    <div class="text-muted extra-small">{{ $notification->recipient?->username ? '@'.$notification->recipient->username : 'User record unavailable' }}</div>
                                </a>
                            </td>
                            <td>
                                @if ($notification->actor)
                                    <a href="{{ route('admin.users.show', $notification->actor) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $notification->actor->full_name ?: '@'.$notification->actor->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$notification->actor->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">System</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-info-soft text-info rounded-pill">{{ str($notification->type)->replace('_', ' ')->title() }}</span>
                                <div class="text-muted extra-small mt-1">{{ $notification->is_read ? 'Read' : 'Unread' }}</div>
                            </td>
                            <td><span class="badge {{ $notification->delivery_status === 'sent' ? 'bg-success-soft text-success' : ($notification->delivery_status === 'pending' ? 'bg-warning-soft text-warning' : 'bg-danger-soft text-danger') }} rounded-pill">{{ str($notification->delivery_status)->title() }}</span></td>
                            <td><span class="text-muted small">{{ $notification->created_at?->format('M d, Y H:i') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No notifications found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">Showing <span class="text-emphasis-dynamic fw-bold">{{ $notifications->firstItem() ?? 0 }}-{{ $notifications->lastItem() ?? 0 }}</span> of <span class="text-emphasis-dynamic fw-bold">{{ number_format($notifications->total()) }}</span> notifications</div>
            {{ $notifications->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const scopeField = document.querySelector('.js-announcement-scope');
    const recipientWrap = document.querySelector('.js-recipient-user');

    if (!scopeField || !recipientWrap) {
        return;
    }

    const syncRecipientVisibility = () => {
        recipientWrap.style.display = scopeField.value === 'single' ? '' : 'none';
    };

    scopeField.addEventListener('change', syncRecipientVisibility);
    syncRecipientVisibility();
});
</script>
@endpush
