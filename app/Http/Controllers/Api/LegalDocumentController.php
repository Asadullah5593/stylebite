<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Legal documents for the app, plus acceptance recording.
 *
 * Reading is public: a user must be able to see the terms before signing up.
 * Accepting requires a session, because an acceptance is only meaningful when
 * it is attached to a person.
 */
class LegalDocumentController extends Controller
{
    public function show(string $key): JsonResponse
    {
        abort_unless(array_key_exists($key, LegalDocument::KEYS), 404);

        $document = LegalDocument::current($key);

        if (! $document) {
            return response()->json([
                'status_code' => 0,
                'message' => 'This document has not been published yet.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Document fetched successfully.',
            'document' => [
                'key' => $document->key,
                'version' => $document->version,
                'title' => $document->title,
                'body' => $document->body,
                'paragraphs' => $document->paragraphs(),
                'summary_of_changes' => $document->summary_of_changes,
                'requires_reacceptance' => $document->requires_reacceptance,
                'published_at' => $document->published_at?->toISOString(),
            ],
        ]);
    }

    /**
     * What the signed-in user still needs to agree to. The app can call this
     * after login and show a consent screen only when something is outstanding.
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        $pending = [];

        foreach (array_keys(LegalDocument::KEYS) as $key) {
            $document = LegalDocument::current($key);

            if (! $document) {
                continue;
            }

            $accepted = LegalAcceptance::query()
                ->where('user_id', $user->id)
                ->where('legal_document_id', $document->id)
                ->exists();

            // Only a version flagged as a material change forces a new consent;
            // otherwise a first-time acceptance is enough.
            if (! $accepted) {
                $pending[] = [
                    'key' => $document->key,
                    'version' => $document->version,
                    'title' => $document->title,
                    'requires_reacceptance' => $document->requires_reacceptance,
                ];
            }
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Pending documents fetched successfully.',
            'pending' => $pending,
            'has_pending' => $pending !== [],
        ]);
    }

    public function accept(Request $request, string $key): JsonResponse
    {
        abort_unless(array_key_exists($key, LegalDocument::KEYS), 404);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        $document = LegalDocument::current($key);

        if (! $document) {
            return response()->json([
                'status_code' => 0,
                'message' => 'This document has not been published yet.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Accepting a stale version must not count: the client is out of date.
        if ((int) $validated['version'] !== (int) $document->version) {
            return response()->json([
                'status_code' => 0,
                'message' => 'A newer version is available. Please reload it before accepting.',
                'current_version' => $document->version,
            ], Response::HTTP_CONFLICT);
        }

        LegalAcceptance::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'legal_document_id' => $document->id],
            [
                'document_key' => $document->key,
                'document_version' => $document->version,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ? Str::limit($request->userAgent(), 255, '') : null,
                'accepted_at' => now(),
            ]
        );

        return response()->json([
            'status_code' => 1,
            'message' => 'Thanks — your acceptance has been recorded.',
            'accepted' => ['key' => $document->key, 'version' => $document->version],
        ]);
    }
}
