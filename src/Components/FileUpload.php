<?php

namespace Xelqun\Forms\Components;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;
use Xelqun\Forms\Components\Concerns\HandlesUploads;
use Xelqun\Forms\Contracts\NamesStoredFiles;
use Xelqun\Forms\Contracts\PersistsItself;

/**
 * Files are uploaded to a temporary endpoint as soon as they are picked, so
 * the state only ever holds a short token ("xq-tmp:...") or a stored path.
 * Saving the form stores the file on its final disk, see UploadBatch.
 *
 * @api
 */
class FileUpload extends Field implements NamesStoredFiles, PersistsItself
{
    use HandlesUploads;

    protected ?string $relationship = null;

    protected string $pathColumn = 'path';

    protected ?string $orderColumn = null;

    public function getType(): string
    {
        return 'file-upload';
    }

    /**
     * Keep each file as a row of a HasMany or MorphMany relationship (defaults to
     * the field name) instead of in a column. This turns multiple() on. A row whose file is
     * removed is deleted, and the stored file stays on the disk.
     */
    public function relationship(?string $name = null, string $pathColumn = 'path', ?string $orderColumn = null): static
    {
        $this->relationship = $name ?? $this->name;
        $this->pathColumn = $pathColumn;
        $this->orderColumn = $orderColumn;

        return $this->multiple();
    }

    public function shouldPersistItself(): bool
    {
        return $this->relationship !== null && $this->storesFiles();
    }

    /**
     * @param  Model|array<string, mixed>|null  $source
     */
    public function fillState(Model|array|null $source): void
    {
        if ($this->relationship === null || ! $source instanceof Model || ! $source->exists) {
            parent::fillState($source);

            return;
        }

        $query = $this->getRelationship($source)->getQuery();

        if ($this->orderColumn !== null) {
            $query->orderBy($this->orderColumn);
        }

        $this->getForm()->setStateValue($this->getStatePath(), $query->pluck($this->pathColumn)->all());
    }

    /**
     * Rows of files that stay are kept, so anything else on them survives.
     */
    public function persist(Model $record, mixed $state): void
    {
        $relationship = $this->getRelationship($record);
        $rows = $relationship->get();
        $existing = $rows->keyBy($this->pathColumn);
        $files = array_values((array) $state);

        foreach ($files as $position => $file) {
            $attributes = [$this->pathColumn => $file];

            if ($this->orderColumn !== null) {
                $attributes[$this->orderColumn] = $position;
            }

            ($existing->get($file) ?? $relationship->make())->forceFill($attributes)->save();
        }

        $rows->reject(fn (Model $row): bool => in_array($row->getAttribute($this->pathColumn), $files, true))
            ->each(fn (Model $row) => $row->delete());
    }

    /**
     * @return HasMany<Model, Model>|MorphMany<Model, Model>
     */
    private function getRelationship(Model $record): HasMany|MorphMany
    {
        $relationship = $record->{$this->relationship}();

        if (! $relationship instanceof HasMany && ! $relationship instanceof MorphMany) {
            throw new LogicException("FileUpload relationship [{$this->relationship}] must be a HasMany or MorphMany relationship.");
        }

        return $relationship;
    }
}
