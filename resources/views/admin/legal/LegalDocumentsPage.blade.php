@extends('admin.layouts.app')

@section('content')
<div class="users-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Legal Documents</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Legal Documents</h1>
            <p class="text-muted small mb-0">Privacy Policy and Terms of Service, with version history and acceptance records</p>
        </div>
    </div>

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    <div class="row g-4">
        @foreach ($documents as $document)
            <div class="col-12 col-lg-6">
                <div class="glass rounded-4 p-4 border border-white-05 h-100">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="h6 fw-bold mb-1">{{ $document['label'] }}</h2>
                            @if ($document['current'])
                                <p class="text-muted extra-small mb-0">
                                    Live: <span class="fw-bold">v{{ $document['current']->version }}</span>
                                    · published {{ $document['current']->published_at?->format('M d, Y') }}
                                </p>
                            @else
                                <p class="text-warning extra-small mb-0">Not published yet</p>
                            @endif
                        </div>
                        @can('legal.manage')
                            <a href="{{ route('admin.legal.edit', $document['key']) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </a>
                        @endcan
                    </div>

                    @if ($document['current'])
                        <div class="bg-dark-soft rounded-3 p-3 mb-3">
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted">Accepted by</span>
                                <span class="fw-bold">{{ number_format($document['acceptances']) }} of {{ number_format($activeUsers) }} active users</span>
                            </div>
                        </div>
                    @endif

                    <h3 class="text-muted extra-small text-uppercase fw-bold mb-2">Version history</h3>
                    @forelse ($document['versions'] as $version)
                        <div class="d-flex align-items-center justify-content-between gap-2 py-2 border-bottom border-white-05 small">
                            <span>
                                <a href="{{ route('admin.legal.show', $version) }}" class="text-decoration-none fw-semibold">v{{ $version->version }}</a>
                                @if ($version->is_published)
                                    <span class="badge bg-success-soft text-success rounded-pill ms-1" style="font-size: 0.6rem;">Published</span>
                                @else
                                    <span class="badge bg-warning-soft text-warning rounded-pill ms-1" style="font-size: 0.6rem;">Draft</span>
                                @endif
                                @if ($version->requires_reacceptance)
                                    <span class="badge bg-danger-soft text-danger rounded-pill ms-1" style="font-size: 0.6rem;">Re-acceptance</span>
                                @endif
                            </span>
                            <span class="text-muted extra-small">{{ $version->updated_at?->format('M d, Y') }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No versions yet.</p>
                    @endforelse

                    @can('legal.manage')
                        @if ($document['current'])
                            <a href="{{ route('admin.legal.acceptances.export', $document['key']) }}" class="btn btn-sm btn-outline-dynamic rounded-3 mt-3">
                                <i class="bi bi-download me-1"></i>Export acceptances (CSV)
                            </a>
                        @endif
                    @endcan
                </div>
            </div>
        @endforeach
    </div>

    <div class="glass rounded-4 p-3 mt-4 border border-white-05 text-muted small">
        <i class="bi bi-info-circle me-2"></i>
        Publishing never overwrites an old version — it creates the next one, so the exact wording a user agreed to stays recoverable. Tick <span class="fw-bold">requires re-acceptance</span> only for material changes: it asks every user to agree again.
    </div>
</div>
@endsection
