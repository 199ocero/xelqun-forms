<?php

namespace Xelqun\Forms\Uploads;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mime\MimeTypes;

/**
 * Holds files between "picked in the browser" and "form saved".
 */
final class TemporaryUploadStore
{
    public const string TOKEN_PREFIX = 'xq-tmp:';

    /**
     * @param  string  $scope  The field this upload is for. It can only be used there.
     * @return array{token: string, name: string, size: int, mime: string|null}
     */
    public function store(UploadedFile $file, string $scope): array
    {
        $id = Str::random(40);
        $metadata = [
            'name' => $this->sanitizeName($file->getClientOriginalName()),
            'size' => (int) $file->getSize(),
            'mime' => $file->getMimeType(),
            'extension' => $this->safeExtension($file),
            'scope' => $scope,
            'session' => $this->sessionHash(),
            'created_at' => time(),
        ];

        $this->disk()->putFileAs($this->directory(), $file, $id);
        $this->disk()->put($this->metadataPath($id), (string) json_encode($metadata));

        return ['token' => self::TOKEN_PREFIX.$id, 'name' => $metadata['name'], 'size' => $metadata['size'], 'mime' => $metadata['mime']];
    }

    public function isToken(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::TOKEN_PREFIX);
    }

    /**
     * The temporary file, for code that handles it itself.
     */
    public function open(string $id): TemporaryUpload
    {
        $metadata = $this->metadata($id) ?? [];

        return new TemporaryUpload(
            (string) ($metadata['name'] ?? $id),
            (int) ($metadata['size'] ?? 0),
            $metadata['mime'] ?? null,
            $this->disk(),
            $this->directory().'/'.$id,
        );
    }

    public function idFromToken(string $token): string
    {
        return substr($token, strlen(self::TOKEN_PREFIX));
    }

    /**
     * @return array{name: string, size: int, mime: string|null, extension: string, scope: string, session: string, created_at: int}|null
     */
    public function metadata(string $id): ?array
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $id) !== 1 || ! $this->disk()->exists($this->metadataPath($id))) {
            return null;
        }

        $metadata = json_decode((string) $this->disk()->get($this->metadataPath($id)), true);

        return is_array($metadata) ? $metadata : null;
    }

    /**
     * The upload exists, has not expired, and was made in the current session
     * for this field, so a file can't skip a stricter field's upload rules.
     */
    public function isUsable(string $id, string $scope): bool
    {
        $metadata = $this->metadata($id);

        return $metadata !== null
            && hash_equals($metadata['session'], $this->sessionHash())
            && hash_equals($metadata['scope'], $scope)
            && $metadata['created_at'] >= time() - $this->ttlMinutes() * 60;
    }

    /**
     * @return array{name: string, url: null, size: int}|null
     */
    public function describe(string $id): ?array
    {
        $metadata = $this->metadata($id);

        return $metadata === null ? null : ['name' => $metadata['name'], 'url' => null, 'size' => $metadata['size']];
    }

    /**
     * Copy the temporary file to its final disk and return the stored path.
     *
     * The temporary file stays until the caller deletes it. Every call makes a
     * new copy at a new path, so the caller owns the file it gets back.
     */
    public function promote(string $id, string $disk, string $directory, string $visibility, bool $preserveName = false): string
    {
        $metadata = $this->metadata($id) ?? throw new RuntimeException("Temporary upload [{$id}] does not exist.");
        $extension = $metadata['extension'] !== '' ? '.'.$metadata['extension'] : '';
        $suffix = '-'.Str::lower(Str::random(12));
        // The name was cleaned on upload; trimming dots keeps it from being "." or "..".
        $original = trim(pathinfo($metadata['name'], PATHINFO_FILENAME), '. ');
        $basename = $preserveName ? ($original !== '' ? $original : 'file') : (Str::limit(Str::slug($original), 60, '') ?: 'file');
        $path = ltrim($directory.'/'.$basename.($preserveName ? '' : $suffix).$extension, '/');

        // shortcut: two saves of one name at the same moment can both see it free, and the second wins; take a lock per path if that happens.
        if ($preserveName && Storage::disk($disk)->exists($path)) {
            $path = ltrim($directory.'/'.$basename.$suffix.$extension, '/');
        }

        $stream = $this->disk()->readStream($this->directory().'/'.$id);

        try {
            $isStored = Storage::disk($disk)->writeStream($path, $stream, ['visibility' => $visibility]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($isStored === false) {
            throw new RuntimeException("Temporary upload [{$id}] could not be stored on disk [{$disk}].");
        }

        return $path;
    }

    public function delete(string $id): void
    {
        $this->disk()->delete([$this->directory().'/'.$id, $this->metadataPath($id)]);
    }

    /**
     * Delete uploads older than the configured lifetime. Returns how many were removed.
     */
    public function prune(): int
    {
        $removed = 0;

        foreach ($this->disk()->files($this->directory()) as $file) {
            if (! str_ends_with($file, '.json')) {
                continue;
            }

            $id = basename($file, '.json');
            $metadata = $this->metadata($id);

            if ($metadata === null || $metadata['created_at'] < time() - $this->ttlMinutes() * 60) {
                $this->delete($id);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * The extension decides how a web server treats the stored file, so it comes
     * from the detected type. The client's name is kept only if it matches that type.
     */
    private function safeExtension(UploadedFile $file): string
    {
        $clientExtension = Str::lower($file->getClientOriginalExtension());
        $allowed = MimeTypes::getDefault()->getExtensions((string) $file->getMimeType());

        return in_array($clientExtension, $allowed, true) ? $clientExtension : (string) $file->guessExtension();
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('xelqun-forms.uploads.temporary_disk', 'local'));
    }

    private function directory(): string
    {
        return (string) config('xelqun-forms.uploads.temporary_directory', 'xelqun-tmp');
    }

    private function metadataPath(string $id): string
    {
        return $this->directory().'/'.$id.'.json';
    }

    private function ttlMinutes(): int
    {
        return (int) config('xelqun-forms.uploads.temporary_ttl', 1440);
    }

    private function sessionHash(): string
    {
        $request = request();
        $sessionId = $request->hasSession() ? $request->session()->getId() : '';

        return hash('sha256', $sessionId.'|'.config('app.key'));
    }

    private function sanitizeName(string $name): string
    {
        $name = preg_replace('/[^\w.\- ]+/u', '', $name) ?? 'file';

        return Str::limit($name !== '' ? $name : 'file', 120, '');
    }
}
