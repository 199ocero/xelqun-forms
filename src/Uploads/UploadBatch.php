<?php

namespace Xelqun\Forms\Uploads;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Contracts\AcceptsUploads;
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

                if ($this->store->isToken($value)) {
                    data_set($output, $path, $this->promoteFile($component, $this->store->idFromToken($value)));
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
        });
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
     * One upload posted in two rows is stored once.
     *
     * The field's own rules already checked the token, but a plugin can leave
     * them out, and a token made elsewhere could then copy another field's file.
     * So this comes first: another field storing the upload proves nothing here.
     */
    private function promoteFile(AcceptsUploads $field, string $id): string
    {
        if (! $this->store->isUsable($id, $field->getUploadScope())) {
            throw ValidationException::withMessages([
                $field->getStatePath() => __('xelqun-forms::messages.uploads.expired', ['attribute' => $field->getLabel()]),
            ]);
        }

        if (isset($this->stored[$id])) {
            return $this->stored[$id]['path'];
        }

        $disk = $field->getDiskName();
        $path = $this->store->promote($id, $disk, $field->getDirectory(), $field->getVisibility());
        $this->stored[$id] = ['disk' => $disk, 'path' => $path];

        return $path;
    }
}
