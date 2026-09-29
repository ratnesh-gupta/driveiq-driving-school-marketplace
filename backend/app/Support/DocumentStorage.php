<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Private storage for learner / instructor / vehicle documents (DIQ-601).
 *
 * Files live on DOCUMENTS_DISK (default: the private "local" disk; point it at
 * an S3 / DigitalOcean Spaces disk in production). The server always chooses
 * the path, under schools/{school}/{kind}/{owner}/, never the client.
 */
class DocumentStorage
{
    /** Upload rule shared by every document endpoint. */
    public const FILE_RULE = ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'];

    public function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.documents_disk', 'local'));
    }

    public function store(UploadedFile $file, int $schoolId, string $kind, int $ownerId): string
    {
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');

        return $this->disk()->putFileAs(
            "schools/{$schoolId}/{$kind}/{$ownerId}",
            $file,
            Str::uuid()->toString().'.'.$ext
        );
    }

    public function delete(?string $path, int $schoolId): void
    {
        if ($this->belongsToSchool($path, $schoolId)) {
            $this->disk()->delete($path);
        }
    }

    /**
     * Only server-generated paths inside this school's folder are ever served
     * or deleted (older rows could hold arbitrary client-supplied strings).
     */
    public function belongsToSchool(?string $path, int $schoolId): bool
    {
        return is_string($path)
            && str_starts_with($path, "schools/{$schoolId}/")
            && ! str_contains($path, '..');
    }

    /** A display-safe original file name. */
    public static function displayName(UploadedFile $file): string
    {
        $name = preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()) ?: 'document';

        return Str::limit($name, 200, '');
    }
}
