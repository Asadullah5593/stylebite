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

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

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
                    @elseif ($field['type'] === 'json')
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

</div>
@endsection
