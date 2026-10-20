<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\InstructorDocument;
use App\Models\Learner;
use App\Models\LearnerDocument;
use App\Models\VehicleDocument;
use App\Support\DocumentStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads for private documents (DIQ-601), in two steps so a plain link can
 * open the file without a bearer header:
 *  1. GET /documents/{kind}/{id}/link  (authenticated, access-checked)
 *     returns a signed URL valid for LINK_TTL_MINUTES.
 *  2. GET /documents/{kind}/{id}/file  (signature-checked) streams the file.
 */
class DocumentController extends Controller
{
    public const LINK_TTL_MINUTES = 5;

    private const MODELS = [
        'learner' => LearnerDocument::class,
        'instructor' => InstructorDocument::class,
        'vehicle' => VehicleDocument::class,
    ];

    public function link(Request $request, string $kind, int $id, DocumentStorage $storage): JsonResponse
    {
        $doc = $this->find($kind, $id);
        if (! $doc) {
            return response()->json(['message' => 'Document not found'], 404);
        }
        if ($deny = $this->authorizeDocument($request, $kind, $doc)) {
            return $deny;
        }
        if (! $this->hasServableFile($doc, $storage)) {
            return response()->json(['message' => 'No file has been uploaded for this document'], 404);
        }

        $expiresAt = now()->addMinutes(self::LINK_TTL_MINUTES);

        return response()->json([
            'url' => URL::temporarySignedRoute('documents.file', $expiresAt, ['kind' => $kind, 'id' => $id]),
            'expiresAt' => $expiresAt->toISOString(),
        ]);
    }

    public function file(string $kind, int $id, DocumentStorage $storage): StreamedResponse|JsonResponse
    {
        $doc = $this->find($kind, $id);
        if (! $doc || ! $this->hasServableFile($doc, $storage)) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        return $storage->disk()->response(
            $doc->file_path,
            $doc->file_name ?: basename($doc->file_path),
            [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function find(string $kind, int $id): ?Model
    {
        $class = self::MODELS[$kind] ?? null;

        return $class ? $class::withoutGlobalScope('school')->find($id) : null;
    }

    /** Same rules as listing that owner's documents. */
    private function authorizeDocument(Request $request, string $kind, Model $doc): ?JsonResponse
    {
        return match ($kind) {
            'learner' => $this->access()->learner(
                $request,
                Learner::withoutGlobalScope('school')->findOrFail($doc->learner_id),
                allowSelf: true,
            ),
            'instructor' => $this->access()->instructor(
                $request,
                Instructor::withoutGlobalScope('school')->findOrFail($doc->instructor_id),
                allowSelf: true,
            ),
            default => $this->access()->school($request, (int) $doc->school_id),
        };
    }

    /** Legacy rows may hold client-supplied paths; only our own school-scoped files are served. */
    private function hasServableFile(Model $doc, DocumentStorage $storage): bool
    {
        return $storage->belongsToSchool($doc->file_path, (int) $doc->school_id)
            && $storage->disk()->exists($doc->file_path);
    }
}
