<?php

namespace Xelqun\Forms\Uploads;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Holds files between "picked in the browser" and "form saved".
 */
final class TemporaryUploadStore
{
    public const string TOKEN_PREFIX = 'xq-tmp:';

    /**
     * @return array{token: string, name: string, size: int, mime: string|null}
     */
    public function store(UploadedFile $file): array
    {
        $id = Str::random(40);
        $metadata = [
            'name' => $this->sanitizeName($file->getClientOriginalName()),
            'size' => (int) $file->getSize(),
            'mime' => $file->getMimeType(),
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

    public function idFromToken(string $token): string
    {
        return substr($token, strlen(self::TOKEN_PREFIX));
    }

    /**
     * @return array{name: string, size: int, mime: string|null, session: string, created_at: int}|null
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
     * The upload exists, has not expired and was made in the current session.
     */
    public function isUsable(string $id): bool
    {
        $metadata = $this->metadata($id);

        return $metadata !== null
            && hash_equals($metadata['session'], $this->sessionHash())
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
     * Move the temporary file to its final disk and return the stored path.
     */
    public function finalize(string $id, string $disk, string $directory, string $visibility): string
    {
        $metadata = $this->metadata($id) ?? throw new RuntimeException("Temporary upload [{$id}] does not exist.");
        $extension = Str::lower(pathinfo($metadata['name'], PATHINFO_EXTENSION));
        $basename = Str::limit(Str::slug(pathinfo($metadata['name'], PATHINFO_FILENAME)), 60, '') ?: 'file';
        $filename = $basename.'-'.Str::lower(Str::random(12)).($extension !== '' ? '.'.$extension : '');
        $path = ltrim($directory.'/'.$filename, '/');

        $stream = $this->disk()->readStream($this->directory().'/'.$id);
        Storage::disk($disk)->writeStream($path, $stream, ['visibility' => $visibility]);

        if (is_resource($stream)) {
            fclose($stream);
        }

        $this->delete($id);

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
