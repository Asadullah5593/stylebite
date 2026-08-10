@extends('admin.layouts.app')

@section('content')
<div class="users-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Notifications</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Campaigns</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Campaigns</h1>
            <p class="text-muted small mb-0">Push campaigns and their delivery progress</p>
        </div>
        @can('notifications.send')
            <a href="{{ route('admin.notifications.notifications') }}" class="btn bg-primary-gradient text-white fw-bold rounded-3 px-3 shadow-glow border-0">
                <i class="bi bi-send me-2"></i>New Campaign
            </a>
        @endcan
    </div>

    @include('admin.notifications.partials.tabs')

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    <form method="GET" action="{{ route('admin.notifications.campaigns') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search title, message, audience...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Statuses</option>
            @foreach (['pending' => 'Pending', 'running' => 'Running', 'completed' => 'Completed', 'failed' => 'Failed', 'cancelled' => 'Cancelled'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.notifications.campaigns') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Campaign</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Audience</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3" style="min-width: 190px;">Progress</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Outcome</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Sent By</th>
                        <th class="text-end pe-4 text-muted small fw-bold text-uppercase py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        @php
                            $statusClass = match ($campaign->status) {
                                'completed' => 'bg-success-soft text-success',
                                'running' => 'bg-info-soft text-info',
                                'pending' => 'bg-secondary-soft text-muted',
                                'cancelled' => 'bg-warning-soft text-warning',
                                default => 'bg-danger-soft text-danger',
                            };
                            $percent = $campaign->progressPercent();
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small">#{{ $campaign->id }} · {{ $campaign->title }}</div>
                                <div class="text-muted extra-small text-truncate" style="max-width: 320px;">{{ $campaign->body }}</div>
                                <div class="text-muted extra-small">{{ $campaign->created_at?->format('M d, Y H:i') }}</div>
                            </td>
                            <td><span class="text-muted small">{{ $campaign->audience_label ?: $campaign->audience_type }}</span></td>
                            <td>
                                <span class="badge {{ $statusClass }} rounded-pill px-3 py-1 fw-bold text-uppercase" style="font-size: 0.65rem;">{{ $campaign->status }}</span>
                                @if ($campaign->status === 'failed' && $campaign->failure_reason)
                                    <div class="text-danger extra-small mt-1" style="max-width: 220px;">{{ $campaign->failure_reason }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="progress bg-dark-soft" style="height: 6px;">
                                    <div class="progress-bar bg-primary-gradient" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="text-muted extra-small mt-1">
                                    {{ number_format($campaign->processed_count) }} / {{ number_format($campaign->total_recipients) }} ({{ $percent }}%)
                                </div>
                            </td>
                            <td>
                                <div class="text-success extra-small">{{ number_format($campaign->sent_count) }} sent</div>
                                <div class="text-muted extra-small">{{ number_format($campaign->skipped_count) }} skipped</div>
                                @if ($campaign->failed_count > 0)
                                    <div class="text-danger extra-small">{{ number_format($campaign->failed_count) }} failed</div>
                                @endif
                            </td>
                            <td>
                                @if ($campaign->creator)
                                    <div class="small fw-semibold">{{ $campaign->creator->full_name ?: $campaign->creator->username }}</div>
                                    <div class="text-muted extra-small">{{ '@'.$campaign->creator->username }}</div>
                                @else
                                    <span class="text-muted small">System</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                @can('notifications.send')
                                    @unless ($campaign->isFinished())
                                        <form method="POST" action="{{ route('admin.notifications.campaigns.cancel', $campaign) }}"
                                              onsubmit="return confirm('Stop campaign #{{ $campaign->id }}? Recipients already delivered to keep their notification; the rest will never be sent.');">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-warning rounded-3" type="submit">
                                                <i class="bi bi-stop-circle me-1"></i>Stop
                                            </button>
                                        </form>
                                    @endunless
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No campaigns yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $campaigns->firstItem() ?? 0 }}-{{ $campaigns->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($campaigns->total()) }}</span> campaigns
            </div>
            {{ $campaigns->links() }}
        </div>
    </div>

    <div class="glass rounded-4 p-3 mt-4 border border-white-05 text-muted small">
        <i class="bi bi-info-circle me-2"></i>
        Campaigns are delivered by the queue worker in chunks of {{ number_format(config('notifications.campaign_chunk_size', 200)) }} recipients.
        A large audience progresses every time the <span class="fw-bold">queue:work</span> cron fires, so a big send takes minutes to hours rather than completing instantly.
        <span class="fw-bold">Skipped</span> means the recipient has push disabled or no registered device — not a failure.
    </div>
</div>
@endsection
