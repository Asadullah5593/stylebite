<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LegalDocumentController extends Controller
{
    public function index(): View
    {
        $documents = collect(LegalDocument::KEYS)->map(function (string $label, string $key) {
            $current = LegalDocument::current($key);

            return [
                'key' => $key,
                'label' => $label,
                'current' => $current,
                'versions' => LegalDocument::where('key', $key)->orderByDesc('version')->get(),
                'acceptances' => $current
                    ? LegalAcceptance::where('legal_document_id', $current->id)->count()
                    : 0,
            ];
        })->values();

        return view('admin.legal.LegalDocumentsPage', [
            'documents' => $documents,
            'activeUsers' => \App\Models\User::where('status', 'active')->count(),
        ]);
    }

    public function edit(Request $request, string $key): View
    {
        abort_unless(array_key_exists($key, LegalDocument::KEYS), 404);

        $current = LegalDocument::current($key);
        $draft = LegalDocument::where('key', $key)->where('is_published', false)->orderByDesc('version')->first();

        return view('admin.legal.LegalDocumentFormPage', [
            'documentKey' => $key,
            'label' => LegalDocument::KEYS[$key],
            'current' => $current,
            'draft' => $draft,
            'nextVersion' => LegalDocument::nextVersion($key),
        ]);
    }

    /**
     * Saving never edits a published version — it creates the next one, so the
     * exact text a user accepted stays recoverable forever.
     */
    public function store(Request $request, string $key): RedirectResponse
    {
        abort_unless(array_key_exists($key, LegalDocument::KEYS), 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:200000'],
            'summary_of_changes' => ['nullable', 'string', 'max:500'],
            'publish' => ['nullable', 'boolean'],
            'requires_reacceptance' => ['nullable', 'boolean'],
        ], [
            'body.required' => 'The document body cannot be empty.',
            'body.max' => 'The document is too long — please keep it under 200,000 characters.',
        ]);

        $publish = $request->boolean('publish');

        // An unpublished draft is still editable in place; only published
        // versions are frozen.
        $draft = LegalDocument::where('key', $key)->where('is_published', false)->orderByDesc('version')->first();

        $document = $draft ?: new LegalDocument([
            'key' => $key,
            'version' => LegalDocument::nextVersion($key),
        ]);

        $document->fill([
            'key' => $key,
            'version' => $document->version ?: LegalDocument::nextVersion($key),
            'title' => $data['title'],
            'body' => $data['body'],
            'summary_of_changes' => $data['summary_of_changes'] ?? null,
            'requires_reacceptance' => $request->boolean('requires_reacceptance'),
            'is_published' => $publish,
            'published_at' => $publish ? now() : null,
            'created_by_user_id' => auth()->id(),
        ])->save();

        ActivityLog::record(
            eventName: $publish ? 'legal_document_published' : 'legal_document_draft_saved',
            entityType: 'legal_document',
            entityId: $document->id,
            metadata: [
                'key' => $key,
                'version' => $document->version,
                'requires_reacceptance' => $document->requires_reacceptance,
                'summary_of_changes' => $document->summary_of_changes,
            ],
            description: ($publish ? 'Published ' : 'Saved a draft of ').LegalDocument::KEYS[$key]." v{$document->version}",
        );

        return redirect()
            ->route('admin.legal.edit', $key)
            ->with('status', $publish
                ? LegalDocument::KEYS[$key]." v{$document->version} is now live."
                : "Draft saved. It is not visible to users until you publish it.");
    }

    public function show(Request $request, LegalDocument $legalDocument): View
    {
        return view('admin.legal.LegalDocumentVersionPage', [
            'document' => $legalDocument,
            'acceptances' => LegalAcceptance::where('legal_document_id', $legalDocument->id)->count(),
        ]);
    }

    /**
     * Who accepted which version, exported for a compliance request.
     */
    public function exportAcceptances(Request $request, string $key)
    {
        abort_unless(array_key_exists($key, LegalDocument::KEYS), 404);

        $validated = $request->validate([
            'version' => ['nullable', 'integer', 'min:1'],
        ]);

        ActivityLog::record(
            eventName: 'legal_acceptances_exported',
            entityType: 'legal_document',
            entityId: null,
            metadata: ['key' => $key, 'version' => $validated['version'] ?? 'all'],
            description: 'Exported acceptance records for '.LegalDocument::KEYS[$key],
        );

        $query = LegalAcceptance::query()
            ->with('user:id,username,email,full_name')
            ->where('document_key', $key)
            ->when($validated['version'] ?? null, fn ($q, $version) => $q->where('document_version', $version));

        return \App\Support\CsvExport::stream(
            'legal-acceptances-'.$key.'-'.now()->format('Y-m-d').'.csv',
            ['Acceptance ID', 'User ID', 'Username', 'Email', 'Full Name', 'Document', 'Version', 'Accepted At', 'IP Address'],
            fn () => $query->lazyByIdDesc(500)->map(fn (LegalAcceptance $acceptance) => [
                $acceptance->id,
                $acceptance->user_id,
                $acceptance->user?->username,
                $acceptance->user?->email,
                $acceptance->user?->full_name,
                $acceptance->document_key,
                $acceptance->document_version,
                $acceptance->accepted_at?->toDateTimeString(),
                $acceptance->ip_address,
            ])
        );
    }

    public static function tabCounts(): array
    {
        return ['legal' => LegalDocument::count()];
    }
}
