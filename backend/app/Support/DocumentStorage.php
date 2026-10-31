<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Private storage for learner / instructor / vehicle documents (DIQ-601).
 *
 * Files live on DOCUMENTS_DISK (default: the private "local" disk; point it at
 * an S3 / DigitalOcean Spaces disk in production). The server always chooses
 * the path, under schools/{school}/{kind}/{owner}/, never the client.
 *
 * Encryption at rest (BSEC-15): with DOCUMENTS_ENCRYPT=true new files are
 * encrypted with the application key (Laravel Crypt, AES-256 + HMAC) before
 * they reach the disk, and stored under a name ending in ".enc". That suffix is
 * the only marker: files without it (uploaded earlier) are read as plain, so
 * nothing existing breaks. `driveiq:encrypt-documents` converts old files.
 * Downloads of encrypted files are decrypted by the app and streamed; the disk
 * never holds plaintext for them. Rotate APP_KEY only with the old key kept in
 * APP_PREVIOUS_KEYS, or encrypted documents become unreadable.
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

        $dir = "schools/{$schoolId}/{$kind}/{$ownerId}";
        $name = Str::uuid()->toString().'.'.$ext;

        if ($this->shouldEncrypt()) {
            $path = "{$dir}/{$name}".self::ENCRYPTED_SUFFIX;
            $this->disk()->put($path, Crypt::encryptString((string) file_get_contents($file->getRealPath())));

            return $path;
        }

        return $this->disk()->putFileAs($dir, $file, $name);
    }

    public const ENCRYPTED_SUFFIX = '.enc';

    public function shouldEncrypt(): bool
    {
        return (bool) config('filesystems.documents_encrypt', true);
    }

    public function isEncrypted(?string $path): bool
    {
        return is_string($path) && str_ends_with($path, self::ENCRYPTED_SUFFIX);
    }

    /** Plain bytes of a stored file, decrypting when needed. Throws DecryptException on tampering / wrong key. */
    public function contents(string $path): string
    {
        $raw = (string) $this->disk()->get($path);

        return $this->isEncrypted($path) ? Crypt::decryptString($raw) : $raw;
    }

    /**
     * Migration path: re-store a plain file encrypted and return its new path
     * (the caller updates the row, then deletes the old file with delete()).
     * Returns null when the file is already encrypted, missing, or not ours.
     */
    public function encryptExisting(string $path, int $schoolId): ?string
    {
        if ($this->isEncrypted($path) || ! $this->belongsToSchool($path, $schoolId) || ! $this->disk()->exists($path)) {
            return null;
        }

        $new = $path.self::ENCRYPTED_SUFFIX;
        $this->disk()->put($new, Crypt::encryptString((string) $this->disk()->get($path)));

        return $new;
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
