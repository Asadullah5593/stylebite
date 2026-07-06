<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppConfig;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $presetGroups = $this->presetConfigGroups();
        $selectedPresetGroup = $request->string('preset_group')->toString() ?: array_key_first($presetGroups);

        $configs = AppConfig::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('config_key', 'like', "%{$search}%")
                        ->orWhere('config_value', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('config_group'), function ($query) use ($request) {
                $group = $request->string('config_group')->toString();

                if ($group === 'other') {
                    $query->where(function ($query) {
                        $query->where('config_key', 'not like', 'general.%')
                            ->where('config_key', 'not like', 'branding.%')
                            ->where('config_key', 'not like', 'notifications.%')
                            ->where('config_key', 'not like', 'contests.%')
                            ->where('config_key', 'not like', 'earnings.%')
                            ->where('config_key', 'not like', 'legal.%')
                            ->where('config_key', 'not like', 'uploads.%');
                    });

                    return;
                }

                $query->where('config_key', 'like', $group.'.%');
            })
            ->when($request->filled('value_type'), fn ($query) => $query->where('value_type', $request->string('value_type')))
            ->orderBy('config_key')
            ->paginate(10)
            ->withQueryString();

        $configGroupCounts = [
            'general' => AppConfig::query()->where('config_key', 'like', 'general.%')->count(),
            'branding' => AppConfig::query()->where('config_key', 'like', 'branding.%')->count(),
            'notifications' => AppConfig::query()->where('config_key', 'like', 'notifications.%')->count(),
            'contests' => AppConfig::query()->where('config_key', 'like', 'contests.%')->count(),
            'earnings' => AppConfig::query()->where('config_key', 'like', 'earnings.%')->count(),
            'legal' => AppConfig::query()->where('config_key', 'like', 'legal.%')->count(),
            'uploads' => AppConfig::query()->where('config_key', 'like', 'uploads.%')->count(),
        ];

        $configGroupCounts['other'] = max(AppConfig::count() - array_sum($configGroupCounts), 0);

        $presetKeys = collect($presetGroups)->flatMap(fn ($group) => array_keys($group['fields']))->all();
        $presetConfigs = AppConfig::query()
            ->whereIn('config_key', $presetKeys)
            ->get()
            ->keyBy('config_key');

        return view('admin.settings.AppConfigsPage', compact('configs', 'configGroupCounts', 'presetGroups', 'selectedPresetGroup', 'presetConfigs'));
    }

    public function systemHealth(): View
    {
        $healthStats = $this->healthStats();
        $queueStats = [
            'jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'job_batches' => DB::table('job_batches')->count(),
        ];
        $cacheStats = [
            'entries' => DB::table('cache')->count(),
            'locks' => DB::table('cache_locks')->count(),
            'expiring_today' => DB::table('cache')
                ->whereBetween('expiration', [now()->timestamp, now()->endOfDay()->timestamp])
                ->count(),
        ];

        return view('admin.settings.SystemHealthPage', compact('healthStats', 'queueStats', 'cacheStats'));
    }

    public function updateConfig(Request $request, AppConfig $config)
    {
        $validated = $request->validate([
            'config_value' => ['nullable', 'string'],
        ]);

        $oldValue = $config->config_value;
        $newValue = $validated['config_value'];

        if ($config->value_type === 'json' && filled($newValue)) {
            json_decode($newValue, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->with('status', 'Invalid JSON value provided.');
            }
        }

        if ($config->value_type === 'boolean' && filled($newValue) && ! in_array(strtolower($newValue), ['true', 'false', '0', '1'], true)) {
            return back()->with('status', 'Boolean values should be true, false, 0, or 1.');
        }

        if ($config->value_type === 'number' && filled($newValue) && ! is_numeric($newValue)) {
            return back()->with('status', 'Number value must be numeric.');
        }

        $config->config_value = $newValue;
        $config->save();

        $this->logActivity('app_config_updated', 'app_config', $config->id, [
            'config_key' => $config->config_key,
            'old_value' => $oldValue,
            'new_value' => $config->config_value,
        ]);

        return back()->with('status', 'Config updated successfully.');
    }

    public function storeConfig(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'config_key' => ['required', 'string', 'max:191', 'alpha_dash', 'unique:app_configs,config_key'],
            'config_value' => ['nullable', 'string'],
            'value_type' => ['required', 'in:string,number,boolean,json'],
        ]);

        $configValue = $data['config_value'] ?? null;

        if ($data['value_type'] === 'json' && filled($configValue)) {
            json_decode($configValue, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->with('status', 'Invalid JSON value provided.');
            }
        }

        if ($data['value_type'] === 'boolean' && filled($configValue) && ! in_array(strtolower($configValue), ['true', 'false', '0', '1'], true)) {
            return back()->with('status', 'Boolean values should be true, false, 0, or 1.');
        }

        if ($data['value_type'] === 'number' && filled($configValue) && ! is_numeric($configValue)) {
            return back()->with('status', 'Number value must be numeric.');
        }

        $config = AppConfig::create([
            'config_key' => $data['config_key'],
            'config_value' => $configValue,
            'value_type' => $data['value_type'],
        ]);

        $this->logActivity('app_config_created', 'app_config', $config->id, [
            'config_key' => $config->config_key,
            'value_type' => $config->value_type,
        ]);

        return back()->with('status', 'Config created successfully.');
    }

    public function deleteConfig(AppConfig $config): RedirectResponse
    {
        $this->logActivity('app_config_deleted', 'app_config', $config->id, [
            'config_key' => $config->config_key,
            'value_type' => $config->value_type,
        ]);

        $config->delete();

        return back()->with('status', 'Config deleted successfully.');
    }

    public function savePresetConfigs(Request $request): RedirectResponse
    {
        $presetGroups = $this->presetConfigGroups();
        $fields = collect($presetGroups)->flatMap(fn ($group) => $group['fields'])->all();

        $validated = $request->validate([
            'configs' => ['nullable', 'array'],
            'configs.*' => ['nullable', 'string'],
            'config_uploads' => ['nullable', 'array'],
            'config_uploads.*' => ['nullable', 'file', 'image', 'max:5120'],
            'preset_group' => ['nullable', 'string'],
        ]);

        $textConfigs = $validated['configs'] ?? [];

        foreach ($textConfigs as $configKey => $configValue) {
            if (! array_key_exists($configKey, $fields)) {
                continue;
            }

            $definition = $fields[$configKey];
            $normalizedValue = $this->normalizeConfigValue($definition['type'], $configValue);

            $config = AppConfig::query()->firstOrNew(['config_key' => $configKey]);
            $oldValue = $config->exists ? $config->config_value : null;
            $config->config_value = $normalizedValue;
            $config->value_type = $definition['type'];
            $config->save();

            $this->logActivity($config->wasRecentlyCreated ? 'app_config_created' : 'app_config_updated', 'app_config', $config->id, [
                'config_key' => $config->config_key,
                'old_value' => $oldValue,
                'new_value' => $config->config_value,
                'preset_group' => $validated['preset_group'] ?? null,
            ]);
        }

        $uploadedConfigs = $request->file('config_uploads', []);

        foreach ($fields as $configKey => $definition) {
            if (($definition['input'] ?? null) !== 'file' || ! array_key_exists($configKey, $uploadedConfigs)) {
                continue;
            }

            $uploadedFile = $uploadedConfigs[$configKey] ?? null;

            if (! $uploadedFile) {
                continue;
            }

            $config = AppConfig::query()->firstOrNew(['config_key' => $configKey]);
            $oldValue = $config->exists ? $config->config_value : null;
            $uploadMeta = stylebite_upload_file($uploadedFile, 'uploads/settings');

            $config->config_value = $uploadMeta['file_path'];
            $config->value_type = $definition['type'];
            $config->save();

            $this->deleteManagedSettingsUpload($oldValue, $config->config_value);

            $this->logActivity($config->wasRecentlyCreated ? 'app_config_created' : 'app_config_updated', 'app_config', $config->id, [
                'config_key' => $config->config_key,
                'old_value' => $oldValue,
                'new_value' => $config->config_value,
                'preset_group' => $validated['preset_group'] ?? null,
                'upload' => true,
            ]);
        }

        return redirect()
            ->route('admin.settings.configs', ['preset_group' => $validated['preset_group'] ?? null])
            ->with('status', 'Preset settings saved successfully.');
    }

    public function jobs(Request $request): View
    {
        $jobs = DB::table('jobs')
            ->when($request->filled('q'), fn ($query) => $query->where('queue', 'like', '%'.$request->string('q')->toString().'%'))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.settings.JobsPage', compact('jobs'));
    }

    public function deleteQueuedJob(int $jobId): RedirectResponse
    {
        $job = DB::table('jobs')->where('id', $jobId)->first();

        if (! $job) {
            return back()->with('status', 'Queued job record was not found.');
        }

        DB::table('jobs')->where('id', $jobId)->delete();

        $this->logActivity('queued_job_deleted', 'queued_job', $jobId, [
            'queue' => $job->queue,
            'attempts' => $job->attempts,
        ]);

        return back()->with('status', 'Queued job removed successfully.');
    }

    public function failedJobs(Request $request): View
    {
        $failedJobs = DB::table('failed_jobs')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('uuid', 'like', "%{$search}%")
                        ->orWhere('queue', 'like', "%{$search}%")
                        ->orWhere('exception', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $failedJobStats = [
            'total' => DB::table('failed_jobs')->count(),
            'visible' => $failedJobs->total(),
            'queues' => DB::table('failed_jobs')->distinct('queue')->count('queue'),
        ];

        return view('admin.settings.FailedJobsPage', compact('failedJobs', 'failedJobStats'));
    }

    public function jobBatches(Request $request): View
    {
        $jobBatches = DB::table('job_batches')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();
                $query->where('id', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.settings.JobBatchesPage', compact('jobBatches'));
    }

    public function cacheEntries(Request $request): View
    {
        $cacheEntries = DB::table('cache')
            ->when($request->filled('q'), fn ($query) => $query->where('key', 'like', '%'.$request->string('q')->toString().'%'))
            ->orderBy('key')
            ->paginate(10)
            ->withQueryString();

        $cacheStats = [
            'total' => DB::table('cache')->count(),
            'visible' => $cacheEntries->total(),
            'expiring_today' => DB::table('cache')
                ->whereBetween('expiration', [now()->timestamp, now()->endOfDay()->timestamp])
                ->count(),
        ];

        return view('admin.settings.CachePage', compact('cacheEntries', 'cacheStats'));
    }

    public function retryFailedJob(int $failedJobId)
    {
        $failedJob = DB::table('failed_jobs')->where('id', $failedJobId)->first();

        if (! $failedJob) {
            return back()->with('status', 'Failed job record was not found.');
        }

        Artisan::call('queue:retry', ['id' => [$failedJobId]]);

        $this->logActivity('failed_job_retried', 'failed_job', $failedJobId, [
            'uuid' => $failedJob->uuid,
            'queue' => $failedJob->queue,
            'connection' => $failedJob->connection,
        ]);

        return back()->with('status', 'Failed job was queued for retry.');
    }

    public function deleteFailedJob(int $failedJobId)
    {
        $failedJob = DB::table('failed_jobs')->where('id', $failedJobId)->first();

        if (! $failedJob) {
            return back()->with('status', 'Failed job record was not found.');
        }

        Artisan::call('queue:forget', ['id' => $failedJobId]);

        $this->logActivity('failed_job_deleted', 'failed_job', $failedJobId, [
            'uuid' => $failedJob->uuid,
            'queue' => $failedJob->queue,
            'connection' => $failedJob->connection,
        ]);

        return back()->with('status', 'Failed job was removed from the failed-jobs list.');
    }

    public function clearCachePrefix(Request $request)
    {
        $validated = $request->validate([
            'prefix' => ['required', 'string', 'max:190'],
        ]);

        $prefix = trim($validated['prefix']);

        $deleted = DB::table('cache')
            ->where('key', 'like', $prefix.'%')
            ->delete();

        DB::table('cache_locks')
            ->where('key', 'like', $prefix.'%')
            ->delete();

        $this->logActivity('cache_prefix_cleared', 'cache', null, [
            'prefix' => $prefix,
            'deleted_count' => $deleted,
        ]);

        return back()->with('status', 'Cache prefix cleared successfully.');
    }

    public function clearExpiredCacheEntries(): RedirectResponse
    {
        $now = now()->timestamp;

        $deletedEntries = DB::table('cache')
            ->where('expiration', '<=', $now)
            ->delete();

        $deletedLocks = DB::table('cache_locks')
            ->where('expiration', '<=', $now)
            ->delete();

        $this->logActivity('expired_cache_cleared', 'cache', null, [
            'deleted_entries' => $deletedEntries,
            'deleted_locks' => $deletedLocks,
        ]);

        return back()->with('status', 'Expired cache entries cleared successfully.');
    }

    public function cacheLocks(Request $request): View
    {
        $cacheLocks = DB::table('cache_locks')
            ->when($request->filled('q'), fn ($query) => $query->where('key', 'like', '%'.$request->string('q')->toString().'%'))
            ->orderBy('key')
            ->paginate(10)
            ->withQueryString();

        return view('admin.settings.CacheLocksPage', compact('cacheLocks'));
    }

    public function deleteCacheLock(string $cacheKey): RedirectResponse
    {
        $cacheLock = DB::table('cache_locks')->where('key', $cacheKey)->first();

        if (! $cacheLock) {
            return back()->with('status', 'Cache lock record was not found.');
        }

        DB::table('cache_locks')->where('key', $cacheKey)->delete();

        $this->logActivity('cache_lock_deleted', 'cache_lock', null, [
            'key' => $cacheKey,
            'owner' => $cacheLock->owner,
        ]);

        return back()->with('status', 'Cache lock deleted successfully.');
    }

    public function migrations(Request $request): View
    {
        $migrations = DB::table('migrations')
            ->when($request->filled('q'), fn ($query) => $query->where('migration', 'like', '%'.$request->string('q')->toString().'%'))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.settings.MigrationsPage', compact('migrations'));
    }

    public static function tabCounts(): array
    {
        return [
            'configs' => AppConfig::count(),
            'jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'job_batches' => DB::table('job_batches')->count(),
            'cache' => DB::table('cache')->count(),
            'cache_locks' => DB::table('cache_locks')->count(),
            'migrations' => DB::table('migrations')->count(),
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

    private function healthStats(): array
    {
        return [
            'queued_jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'cache_entries' => DB::table('cache')->count(),
            'cache_locks' => DB::table('cache_locks')->count(),
            'migrations' => DB::table('migrations')->count(),
            'latest_migration' => DB::table('migrations')->orderByDesc('id')->value('migration'),
        ];
    }

    private function presetConfigGroups(): array
    {
        return [
            'general' => [
                'label' => 'General',
                'fields' => [
                    'general.site_name' => ['label' => 'Site Name', 'type' => 'string', 'placeholder' => 'Stylebite'],
                    'general.site_tagline' => ['label' => 'Site Tagline', 'type' => 'string', 'placeholder' => 'Style-first social experiences'],
                    'general.support_email' => ['label' => 'Support Email', 'type' => 'string', 'placeholder' => 'support@example.com'],
                    'general.contact_email' => ['label' => 'Contact Email', 'type' => 'string', 'placeholder' => 'hello@example.com'],
                    'general.default_timezone' => ['label' => 'Default Timezone', 'type' => 'string', 'placeholder' => 'Asia/Karachi'],
                    'general.default_locale' => ['label' => 'Default Locale', 'type' => 'string', 'placeholder' => 'en'],
                ],
            ],
            'branding' => [
                'label' => 'Branding',
                'fields' => [
                    'branding.logo_url' => ['label' => 'Primary Logo', 'type' => 'string', 'input' => 'file', 'accept' => 'image/*'],
                    'branding.logo_dark_url' => ['label' => 'Dark Logo', 'type' => 'string', 'input' => 'file', 'accept' => 'image/*'],
                    'branding.app_icon_url' => ['label' => 'App Icon', 'type' => 'string', 'input' => 'file', 'accept' => 'image/*'],
                    'branding.favicon_url' => ['label' => 'Favicon', 'type' => 'string', 'input' => 'file', 'accept' => 'image/*'],
                    'branding.og_image_url' => ['label' => 'Social Share Image', 'type' => 'string', 'input' => 'file', 'accept' => 'image/*'],
                    'branding.primary_color' => ['label' => 'Primary Brand Color', 'type' => 'string', 'placeholder' => '#111111'],
                ],
            ],
            'email' => [
                'label' => 'Email',
                'fields' => [
                    'notifications.from_name' => ['label' => 'Sender Name', 'type' => 'string', 'placeholder' => 'Stylebite'],
                    'notifications.from_email' => ['label' => 'Sender Email', 'type' => 'string', 'placeholder' => 'no-reply@example.com'],
                    'notifications.reply_to_email' => ['label' => 'Reply-To Email', 'type' => 'string', 'placeholder' => 'support@example.com'],
                    'notifications.smtp_host' => ['label' => 'SMTP Host', 'type' => 'string', 'placeholder' => 'smtp.mailtrap.io'],
                    'notifications.smtp_port' => ['label' => 'SMTP Port', 'type' => 'number', 'placeholder' => '587'],
                    'notifications.smtp_encryption' => ['label' => 'SMTP Encryption', 'type' => 'string', 'placeholder' => 'tls'],
                ],
            ],
            'notifications' => [
                'label' => 'Notifications',
                'fields' => [
                    'notifications.push_enabled' => ['label' => 'Push Notifications Enabled', 'type' => 'boolean', 'placeholder' => 'true'],
                    'notifications.email_enabled' => ['label' => 'Email Notifications Enabled', 'type' => 'boolean', 'placeholder' => 'true'],
                    'notifications.contest_digest_enabled' => ['label' => 'Contest Digest Enabled', 'type' => 'boolean', 'placeholder' => 'true'],
                    'notifications.delivery_retry_limit' => ['label' => 'Delivery Retry Limit', 'type' => 'number', 'placeholder' => '3'],
                ],
            ],
            'uploads' => [
                'label' => 'Uploads',
                'fields' => [
                    'uploads.max_image_mb' => ['label' => 'Max Image Size (MB)', 'type' => 'number', 'placeholder' => '10'],
                    'uploads.max_video_mb' => ['label' => 'Max Video Size (MB)', 'type' => 'number', 'placeholder' => '100'],
                    'uploads.allowed_image_types' => ['label' => 'Allowed Image Types (JSON)', 'type' => 'json', 'placeholder' => '["jpg","png","webp"]'],
                    'uploads.allowed_video_types' => ['label' => 'Allowed Video Types (JSON)', 'type' => 'json', 'placeholder' => '["mp4","mov"]'],
                ],
            ],
            'legal' => [
                'label' => 'Legal',
                'fields' => [
                    'legal.privacy_policy_url' => ['label' => 'Privacy Policy URL', 'type' => 'string', 'placeholder' => 'https://.../privacy-policy'],
                    'legal.terms_url' => ['label' => 'Terms URL', 'type' => 'string', 'placeholder' => 'https://.../terms'],
                    'legal.delete_account_url' => ['label' => 'Delete Account URL', 'type' => 'string', 'placeholder' => 'https://.../delete-account'],
                    'legal.company_name' => ['label' => 'Company Name', 'type' => 'string', 'placeholder' => 'Stylebite Inc.'],
                ],
            ],
        ];
    }

    private function normalizeConfigValue(string $type, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if ($type === 'json') {
            json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                abort(422, 'Invalid JSON value provided.');
            }
        }

        if ($type === 'boolean') {
            $lower = strtolower($value);

            if (! in_array($lower, ['true', 'false', '0', '1'], true)) {
                abort(422, 'Boolean values should be true, false, 0, or 1.');
            }

            return in_array($lower, ['true', '1'], true) ? '1' : '0';
        }

        if ($type === 'number' && ! is_numeric($value)) {
            abort(422, 'Number value must be numeric.');
        }

        return $value;
    }

    private function deleteManagedSettingsUpload(?string $oldValue, ?string $newValue = null): void
    {
        if (! $this->isManagedSettingsUpload($oldValue) || $oldValue === $newValue) {
            return;
        }

        $absolutePath = public_path($oldValue);

        if (File::exists($absolutePath)) {
            File::delete($absolutePath);
        }
    }

    private function isManagedSettingsUpload(?string $path): bool
    {
        if ($path === null) {
            return false;
        }

        return str_starts_with(trim($path), 'uploads/settings/');
    }
}
