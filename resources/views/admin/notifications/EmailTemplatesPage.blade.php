@extends('admin.layouts.app')

@section('content')
<div class="users-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Notifications</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Email Templates</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Email Templates</h1>
            <p class="text-muted small mb-0">The wording of every email Stylebite sends</p>
        </div>
    </div>

    @include('admin.notifications.partials.tabs')

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    <form method="GET" action="{{ route('admin.email_templates.index') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search name, key or subject...">
        </div>

        <select name="category" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Categories</option>
            @foreach (['transactional' => 'Transactional', 'contest' => 'Contest', 'announcement' => 'Announcement'] as $value => $label)
                <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.email_templates.index') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Template</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Subject</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Category</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">State</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Last Edited</th>
                        <th class="text-end pe-4 text-muted small fw-bold text-uppercase py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small">{{ $template->name }}</div>
                                <div class="text-muted extra-small">{{ $template->key }}</div>
                            </td>
                            <td><span class="text-muted small">{{ $template->subject }}</span></td>
                            <td>
                                <span class="badge {{ $template->category === 'transactional' ? 'bg-info-soft text-info' : 'bg-warning-soft text-warning' }} rounded-pill px-3 py-1 fw-bold text-uppercase" style="font-size: 0.65rem;">
                                    {{ $template->category }}
                                </span>
                            </td>
                            <td>
                                @if ($template->is_active)
                                    <span class="d-flex align-items-center gap-2"><span class="dot-indicator bg-success"></span><span class="small">Active</span></span>
                                @else
                                    <span class="d-flex align-items-center gap-2"><span class="dot-indicator bg-warning"></span><span class="small">Using built-in</span></span>
                                @endif
                            </td>
                            <td>
                                @if ($template->editor)
                                    <div class="small">{{ $template->editor->full_name ?: $template->editor->username }}</div>
                                @endif
                                <div class="text-muted extra-small">{{ $template->updated_at?->diffForHumans() }}</div>
                            </td>
                            <td class="text-end pe-4">
                                @can('email_templates.manage')
                                    <a href="{{ route('admin.email_templates.edit', $template) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No templates found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass rounded-4 p-3 mt-4 border border-white-05 text-muted small">
        <i class="bi bi-shield-check me-2"></i>
        Every template has built-in wording as a safety net. If a template is deactivated, left blank, or deleted, Stylebite falls back to that wording and the email still goes out — so editing these can never stop a user receiving a login or password-reset code.
    </div>
</div>
@endsection
