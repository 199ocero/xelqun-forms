<?php

namespace Xelqun\Forms\Uploads;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Contracts\AcceptsUploads;
use Xelqun\Forms\Contracts\EmbedsUploads;
use Xelqun\Forms\Contracts\NamesStoredFiles;
use Xelqun\Forms\Form;
use Xelqun\Forms\Support\StatePath;

/**
 * The uploads of one save. Files are copied to their final disk before the
 * database transaction, and the temporary files stay until it commits, so a
 * failed save leaves no stored files and the user can submit the same uploads
 * again. Each save gets its own copies, so a save that fails can never delete
 * a file that another save of the same upload committed.
 */
final class UploadBatch
{
    /** @var array<string, array{disk: string, path: string}> Upload id => where it was stored. */
    private array $stored = [];

    /** @var array<int, array{disk: string, path: string}> Stored files this save leaves behind. */
    private array $removed = [];

    private bool $isSettled = false;

    public function __construct(private readonly TemporaryUploadStore $store) {}

    /**
     * Copy every upload in the data to its final disk and replace its token
     * with the stored path. If one fails, the copies made so far are deleted.
     *
     * @param  array<string, mixed>  $data  Validated, row-keyed data.
     * @return array<string, mixed>
     */
    public function promote(Form $form, array $data): array
    {
        $output = [StatePath::ROOT => $data];

        try {
            $form->walk(function (Component $component) use (&$output): void {
                if (! $component instanceof AcceptsUploads) {
                    return;
                }

                $path = $component->getStatePath();
                $value = data_get($output, $path);

                if ($component instanceof EmbedsUploads) {
                    // A field left out of the output stays out: writing null here would wipe its column.
                    if (is_string($value)) {
                        data_set($output, $path, $component->replaceUploads($value, fn (string $token): string => $this->promoteToken($component, $token)));
                    }
                } elseif (is_array($value)) {
                    $stored = array_map(fn (mixed $file): mixed => $this->promoteToken($component, $file), $value);
                    data_set($output, $path, $stored);
                    $this->storeFileNames($component, $value, $stored, true, $output);
                } elseif ($this->store->isToken($value)) {
                    $stored = $this->promoteToken($component, $value);
                    data_set($output, $path, $stored);
                    $this->storeFileNames($component, [$value], [$stored], false, $output);
                }
            });
        } catch (Throwable $exception) {
            $this->rollback();

            throw $exception;
        }

        return $output[StatePath::ROOT];
    }

    /**
     * Tie these files to the current database transaction: a commit drops the
     * temporary files and a rollback deletes the stored copies. Call it inside
     * the transaction, because an exception after the commit is not a rollback
     * and must leave the stored files alone. With no transaction, it commits.
     */
    public function settleWithTransaction(Connection $connection): void
    {
        $this->isSettled = true;

        $connection->afterRollBack($this->rollback(...));

        $connection->afterCommit(function (): void {
            foreach (array_keys($this->stored) as $id) {
                $this->store->delete($id);
            }

            foreach ($this->removed as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
        });
    }

    /**
     * Note the stored files this save drops, for fields with deleteRemovedFiles().
     * They go once the save commits, so a rollback keeps them. save() calls this
     * and validate() doesn't, because only save() knows the data was written.
     *
     * @param  array<string, mixed>  $data  Validated, row-keyed data with stored paths.
     */
    public function markRemovedFiles(Form $form, array $data): void
    {
        $output = [StatePath::ROOT => $data];
        $initial = $form->getInitialValue(StatePath::ROOT);

        $this->eachTemplateField($form->getComponents(), function (Field $field) use ($output, $initial): void {
            if (! $field->deletesRemovedFiles() || $field->isHiddenOnServer()) {
                return;
            }

            $pattern = $field->getStatePathPattern();
            $segments = array_slice(explode('.', $pattern), 1);
            $removed = $this->findRemoved($segments, $initial, $output[StatePath::ROOT], false, $this->getRowTypes($field));
            $kept = $this->filesIn(StatePath::get($output, $pattern));

            foreach (array_diff($removed, $kept) as $path) {
                $this->removed[] = ['disk' => $this->diskOf($field), 'path' => $path];
            }
        });
    }

    /**
     * Blocks can share a field name, so a path alone doesn't say whose file it
     * is. By the number of path segments after the row they apply to.
     *
     * @return array<int, string>
     */
    private function getRowTypes(Field $field): array
    {
        $types = [];
        $depth = substr_count($field->getStatePathPattern(), '.');

        for ($component = $field->getParent(); $component !== null; $component = $component->getParent()) {
            if (($type = $component->getRowType()) !== null) {
                $types[$depth - substr_count($component->getContainerPattern(), '.')] = $type;
            }
        }

        return $types;
    }

    private function diskOf(Field $field): string
    {
        return $field instanceof AcceptsUploads ? $field->getDiskName() : (string) config('xelqun-forms.uploads.disk');
    }

    /**
     * @param  array<int, Component>  $components
     * @param  Closure(Field): void  $callback
     */
    private function eachTemplateField(array $components, Closure $callback): void
    {
        Form::eachField($components, function (Field $field) use ($callback): void {
            $callback($field);

            foreach ($field->getTemplateGroups() as $group) {
                $this->eachTemplateField($group, $callback);
            }
        });
    }

    /**
     * Walk a field's path through what the form was filled with. A row that's
     * gone takes its files with it. A key the output simply doesn't have means the
     * field was left out, by a condition for example, and that removes nothing.
     *
     * @param  array<int, string>  $segments
     * @param  array<int, string>  $rowTypes  See getRowTypes().
     * @return array<int, string>
     */
    private function findRemoved(array $segments, mixed $initial, mixed $current, bool $isGone, array $rowTypes): array
    {
        if ($segments === []) {
            return $isGone ? $this->filesIn($initial) : array_values(array_diff($this->filesIn($initial), $this->filesIn($current)));
        }

        $segment = array_shift($segments);

        if (! is_array($initial) || (! $isGone && ! is_array($current))) {
            return [];
        }

        if ($segment === '*') {
            $found = [];

            $type = $rowTypes[count($segments)] ?? null;

            foreach ($initial as $key => $row) {
                if ($type !== null && data_get($row, 'type') !== $type) {
                    continue;
                }

                $rowIsGone = $isGone || ! array_key_exists($key, $current);

                array_push($found, ...$this->findRemoved($segments, $row, $rowIsGone ? null : $current[$key], $rowIsGone, $rowTypes));
            }

            return $found;
        }

        if (! array_key_exists($segment, $initial) || (! $isGone && ! array_key_exists($segment, $current))) {
            return [];
        }

        return $this->findRemoved($segments, $initial[$segment], $isGone ? null : $current[$segment], $isGone, $rowTypes);
    }

    /**
     * @return array<int, string>
     */
    private function filesIn(mixed $value): array
    {
        return array_values(array_filter(Arr::flatten(Arr::wrap($value)), fn (mixed $file): bool => is_string($file) && $file !== ''));
    }

    /**
     * The transaction never got as far as settling, so no rollback will run.
     */
    public function discardUnlessSettled(): void
    {
        if (! $this->isSettled) {
            $this->rollback();
        }
    }

    /**
     * Only this batch's copies are deleted, and the temporary files stay, so
     * their tokens remain valid.
     */
    private function rollback(): void
    {
        foreach ($this->stored as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }
    }

    /**
     * A stored path passes through, because a list can mix old files and new ones.
     */
    /**
     * Write each file's original name where storeFileNamesIn() says. A file
     * that was already stored keeps the name it had.
     *
     * @param  array<array-key, mixed>  $before  Tokens and paths as posted.
     * @param  array<array-key, mixed>  $after  The same, stored.
     * @param  array<string, mixed>  $output
     */
    private function storeFileNames(AcceptsUploads $field, array $before, array $after, bool $isList, array &$output): void
    {
        if (! $field instanceof NamesStoredFiles || ($namesPath = $field->getFileNamesStatePath()) === null) {
            return;
        }

        $previous = data_get($output, $namesPath);
        $names = [];

        foreach ($before as $index => $file) {
            $stored = $after[$index] ?? null;

            if (! is_string($stored)) {
                continue;
            }

            $names[$stored] = $this->store->isToken($file)
                ? ($this->store->describe($this->store->idFromToken($file))['name'] ?? basename($stored))
                : (is_array($previous) && is_string($previous[$stored] ?? null) ? $previous[$stored] : (is_string($previous) ? $previous : basename($stored)));
        }

        data_set($output, $namesPath, $isList ? $names : (array_values($names)[0] ?? null));
    }

    private function promoteToken(AcceptsUploads $field, mixed $file): mixed
    {
        return $this->store->isToken($file) ? $this->promoteFile($field, $this->store->idFromToken($file)) : $file;
    }

    /**
     * One upload posted in two rows is stored once.
     *
     * The field's own rules already checked the token, but a plugin can leave
     * them out, and a token made elsewhere could then copy another field's file.
     * So this comes first: another field storing the upload proves nothing here.
     */
    private function promoteFile(AcceptsUploads $field, string $id): string|TemporaryUpload
    {
        if (! $this->store->isUsable($id, $field->getUploadScope())) {
            throw ValidationException::withMessages([
                $field->getStatePath() => __('xelqun-forms::messages.uploads.expired', ['attribute' => $field->getValidationAttribute()]),
            ]);
        }

        if (! $field->storesFiles()) {
            return $this->store->open($id);
        }

        if (isset($this->stored[$id])) {
            return $this->stored[$id]['path'];
        }

        $disk = $field->getDiskName();
        $path = $this->store->promote($id, $disk, $field->getDirectory(), $field->getVisibility(), $field instanceof NamesStoredFiles && $field->preservesFilenames());
        $this->stored[$id] = ['disk' => $disk, 'path' => $path];

        return $path;
    }
}
