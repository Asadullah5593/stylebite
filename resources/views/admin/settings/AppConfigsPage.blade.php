@extends('admin.layouts.app')

@section('content')
<div class="settings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Settings</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">App Configs</span>
    </nav>

    <div class="mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Settings</h1>
            <p class="text-muted small mb-0">Manage application branding, email, and selected global app config values.</p>
        </div>
    </div>

    @include('admin.settings.partials.tabs')

    @if ($errors->any())
        <div class="alert alert-danger rounded-3 border-0 mb-4">
            <div class="fw-semibold mb-1">Please review the settings form.</div>
            <ul class="mb-0 ps-3 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="glass rounded-4 p-3 mb-4 border border-white-05">
        <div class="d-flex flex-wrap gap-2">
            @foreach ($presetGroups as $groupKey => $group)
                @if (! in_array($groupKey, ['notifications', 'uploads', 'legal'], true))
                    <a href="{{ route('admin.settings.configs', array_merge(request()->except('page'), ['preset_group' => $groupKey])) }}" class="btn {{ $selectedPresetGroup === $groupKey ? 'btn-primary' : 'btn-outline-dynamic' }} rounded-3 px-3 py-2">
                        {{ $group['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>

    <div class="glass rounded-4 p-4 mb-4 border border-white-05">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <div>
                <h2 class="h6 fw-bold mb-1">{{ $presetGroups[$selectedPresetGroup]['label'] ?? 'Preset Settings' }}</h2>
                <p class="text-muted small mb-0">Preset fields stored in `app_configs` for faster admin setup.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.settings.preset_configs.save') }}" enctype="multipart/form-data" class="row g-3">
            @csrf
            <input type="hidden" name="preset_group" value="{{ $selectedPresetGroup }}">
            @foreach (($presetGroups[$selectedPresetGroup]['fields'] ?? []) as $configKey => $field)
                @php $configModel = $presetConfigs[$configKey] ?? null; @endphp
                <div class="col-md-6">
                    <label class="form-label small text-muted">{{ $field['label'] }}</label>
                    @if (($field['input'] ?? null) === 'file')
                        <input type="file" name="config_uploads[{{ $configKey }}]" accept="{{ $field['accept'] ?? 'image/*' }}" class="form-control border-0 bg-dark-soft rounded-3">
                        @if (filled($configModel?->config_value))
                            <div class="mt-3 p-3 rounded-3 bg-dark-soft border border-white-05">
                                <div class="text-muted extra-small mb-2">Current file</div>
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <img src="{{ stylebite_asset_url($configModel->config_value) }}" alt="{{ $field['label'] }}" class="rounded-3 border border-white-05" style="width: 72px; height: 72px; object-fit: cover;">
                                    <div>
                                        <a href="{{ stylebite_asset_url($configModel->config_value) }}" target="_blank" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                            <i class="bi bi-box-arrow-up-right me-1"></i>Preview
                                        </a>
                                        <div class="text-muted extra-small mt-2 font-monospace">{{ $configModel->config_value }}</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @elseif ($field['type'] === 'json' || $field['type'] === 'text')
                        <textarea name="configs[{{ $configKey }}]" rows="4" class="form-control border-0 bg-dark-soft rounded-3" placeholder="{{ $field['placeholder'] ?? '' }}">{{ old('configs.'.$configKey, $configModel?->config_value) }}</textarea>
                    @else
                        <input type="text" name="configs[{{ $configKey }}]" class="form-control border-0 bg-dark-soft rounded-3" value="{{ old('configs.'.$configKey, $configModel?->config_value) }}" placeholder="{{ $field['placeholder'] ?? '' }}">
                    @endif
                    <div class="text-muted extra-small mt-1 font-monospace">{{ $configKey }} | {{ strtoupper($field['type']) }}{{ ($field['input'] ?? null) === 'file' ? ' | FILE UPLOAD' : '' }}</div>
                </div>
            @endforeach
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary rounded-3 px-4">
                    <i class="bi bi-check2-circle me-2"></i>Save {{ $presetGroups[$selectedPresetGroup]['label'] ?? 'Settings' }}
                </button>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        @foreach ($configGroupCounts as $groupKey => $groupCount)
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('admin.settings.configs', array_merge(request()->except('page'), ['config_group' => $groupKey])) }}" class="glass rounded-4 p-3 h-100 d-block text-decoration-none border border-white-05">
                    <div class="text-muted extra-small text-uppercase">{{ str($groupKey)->replace('_', ' ')->title() }}</div>
                    <div class="fs-5 fw-bold text-white">{{ number_format($groupCount) }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="glass rounded-4 p-4 mb-4 border border-white-05">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <div>
                <h2 class="h6 fw-bold mb-1">Add config key</h2>
                <p class="text-muted small mb-0">Create a raw `app_configs` entry when a preset field is not enough.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.settings.configs.store') }}" class="row g-3">
            @csrf
            <div class="col-md-4">
                <label class="form-label small text-muted">Config key</label>
                <input type="text" name="config_key" value="{{ old('config_key') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="general.site_name" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Value type</label>
                <select name="value_type" class="form-select border-0 bg-dark-soft rounded-3" required>
                    @foreach (['string', 'number', 'boolean', 'json'] as $type)
                        <option value="{{ $type }}" @selected(old('value_type', 'string') === $type)>{{ strtoupper($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small text-muted">Config value</label>
                <input type="text" name="config_value" value="{{ old('config_value') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Value">
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-outline-dynamic rounded-3 px-4">
                    <i class="bi bi-plus-lg me-2"></i>Create config
                </button>
            </div>
        </form>
    </div>

    <form method="GET" action="{{ route('admin.settings.configs') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <input type="hidden" name="preset_group" value="{{ $selectedPresetGroup }}">
        <div class="position-relative flex-grow-1" style="min-width: 240px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search config keys or values...">
        </div>
        <select name="config_group" class="form-select border-0 bg-dark-soft rounded-3" style="width: auto;">
            <option value="">All groups</option>
            @foreach ($configGroupCounts as $groupKey => $groupCount)
                <option value="{{ $groupKey }}" @selected(request('config_group') === $groupKey)>{{ str($groupKey)->replace('_', ' ')->title() }} ({{ $groupCount }})</option>
            @endforeach
        </select>
        <select name="value_type" class="form-select border-0 bg-dark-soft rounded-3" style="width: auto;">
            <option value="">All types</option>
            @foreach (['string', 'number', 'boolean', 'json'] as $type)
                <option value="{{ $type }}" @selected(request('value_type') === $type)>{{ strtoupper($type) }}</option>
            @endforeach
        </select>
        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.settings.configs', ['preset_group' => $selectedPresetGroup]) }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Key</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Type</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Value</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($configs as $config)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold font-monospace">{{ $config->config_key }}</div>
                                <div class="text-muted extra-small">#{{ $config->id }}</div>
                            </td>
                            <td><span class="badge bg-info-soft text-info rounded-pill">{{ strtoupper($config->value_type) }}</span></td>
                            <td style="min-width: 280px;">
                                <form method="POST" action="{{ route('admin.settings.configs.update', $config) }}" class="d-flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" name="config_value" value="{{ $config->config_value }}" class="form-control form-control-sm border-0 bg-dark-soft rounded-3">
                                    <button type="submit" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">Save</button>
                                </form>
                            </td>
                            <td class="text-end pe-4">
                                <form method="POST" action="{{ route('admin.settings.configs.delete', $config) }}" onsubmit="return confirm('Delete config {{ $config->config_key }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-3">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">No config rows match this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($configs->hasPages())
            <div class="p-3 border-top border-white-05">
                {{ $configs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
