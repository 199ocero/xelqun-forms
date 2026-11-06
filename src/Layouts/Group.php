<?php

namespace Xelqun\Forms\Layouts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use LogicException;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Contracts\PersistsBeforeSave;
use Xelqun\Forms\Eloquent\ModelPersister;

/**
 * Components with no markup of their own. With relationship(), they read and
 * write one related record instead of the form's.
 *
 * @api
 */
class Group extends Layout implements PersistsBeforeSave
{
    protected ?string $relationship = null;

    /**
     * @param  array<int, Component>  $components
     */
    final public function __construct(array $components = [])
    {
        $this->schema($components);
    }

    /**
     * @param  array<int, Component>  $components
     */
    public static function make(array $components = []): static
    {
        return (new static($components))->configure();
    }

    public function getType(): string
    {
        return 'grid';
    }

    /**
     * Edit one HasOne or BelongsTo record with these fields. Their state
     * lives under the relationship's name, so a sibling is read with $get('name')
     * and a field outside with $get('$.name'). A missing record is only created
     * when a field has a value, and a switched-off toggle isn't one, so an
     * optional group can stay blank. While the group is hidden, the record is
     * left alone.
     */
    public function relationship(string $name): static
    {
        $this->relationship = $name;

        return $this;
    }

    public function getName(): string
    {
        return (string) $this->relationship;
    }

    public function shouldPersistItself(): bool
    {
        return $this->relationship !== null;
    }

    public function persistBeforeSave(Model $record, mixed $state): void
    {
        $relationship = $this->getRelationship($record);

        if ($relationship instanceof BelongsTo && ($related = $this->findOrMake($relationship, $state)) !== null) {
            $this->saveRelated($related, $state);
            $relationship->associate($related);
        }
    }

    public function persist(Model $record, mixed $state): void
    {
        $relationship = $this->getRelationship($record);

        if (! $relationship instanceof BelongsTo && ($related = $this->findOrMake($relationship, $state)) !== null) {
            $this->saveRelated($related, $state);
        }
    }

    public function getChildModel(Model $parent): ?Model
    {
        return $this->relationship === null ? null : $this->getRelationship($parent)->getRelated();
    }

    public function getChildSource(Model|array|null $source): Model|array|null
    {
        if ($this->relationship === null) {
            return $source;
        }

        return $source instanceof Model && $source->exists ? $this->getRelationship($source)->first() : null;
    }

    protected function getChildContainer(): string
    {
        return $this->relationship === null ? $this->container : $this->container.'.'.$this->relationship;
    }

    protected function getChildContainerPattern(): string
    {
        return $this->relationship === null ? $this->containerPattern : $this->containerPattern.'.'.$this->relationship;
    }

    /**
     * @param  HasOne<Model, Model>|BelongsTo<Model, Model>  $relationship
     * @return Model|null Null when there is no record and nothing to create one from.
     */
    private function findOrMake(HasOne|BelongsTo $relationship, mixed $state): ?Model
    {
        $related = $relationship->first();

        if ($related !== null) {
            return $related;
        }

        if (array_filter(Arr::flatten((array) $state), fn (mixed $value): bool => $value !== false && filled($value)) === []) {
            return null;
        }

        return $relationship instanceof BelongsTo ? $relationship->getRelated()->newInstance() : $relationship->make();
    }

    private function saveRelated(Model $related, mixed $state): void
    {
        app(ModelPersister::class)->persistScope($related, (array) $state, $this->getChildComponents());
    }

    /**
     * @return HasOne<Model, Model>|BelongsTo<Model, Model>
     */
    private function getRelationship(Model $record): HasOne|BelongsTo
    {
        $relationship = $record->{$this->relationship}();

        if (! ($relationship instanceof HasOne || $relationship instanceof BelongsTo) || $relationship instanceof MorphTo) {
            throw new LogicException("Group relationship [{$this->relationship}] must be a HasOne or BelongsTo relationship.");
        }

        return $relationship;
    }
}
