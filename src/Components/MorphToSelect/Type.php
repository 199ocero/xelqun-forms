<?php

namespace Xelqun\Forms\Components\MorphToSelect;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Xelqun\Forms\Exceptions\FormDefinitionException;

/**
 * One kind of record a MorphToSelect can point at.
 *
 * @api
 */
class Type
{
    protected ?string $titleAttribute = null;

    protected ?string $label = null;

    protected ?Closure $optionLabelFromRecordUsing = null;

    protected ?Closure $modifyOptionsQueryUsing = null;

    /**
     * @param  class-string<Model>  $model
     */
    final public function __construct(protected string $model) {}

    /**
     * @param  class-string<Model>  $model
     */
    public static function make(string $model): static
    {
        return new static($model);
    }

    public function titleAttribute(string $attribute): static
    {
        $this->titleAttribute = $attribute;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Build each option's label from its record.
     *
     * @param  Closure(Model): string  $callback
     */
    public function getOptionLabelFromRecordUsing(Closure $callback): static
    {
        $this->optionLabelFromRecordUsing = $callback;

        return $this;
    }

    /**
     * Limit which records can be chosen. This is also what validation checks,
     * so it is the place to scope records to the current user.
     *
     * @param  Closure(Builder<Model>): (Builder<Model>|null)  $callback
     */
    public function modifyOptionsQueryUsing(Closure $callback): static
    {
        $this->modifyOptionsQueryUsing = $callback;

        return $this;
    }

    public function getMorphClass(): string
    {
        return (new $this->model)->getMorphClass();
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline(class_basename($this->model));
    }

    /**
     * @return array<array-key, string>
     */
    public function getOptions(?string $search = null, ?int $limit = null): array
    {
        $query = $this->newQuery()->orderBy($this->getTitleAttribute())->limit($limit);

        if ($search !== null) {
            $query->where($this->getTitleAttribute(), 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%');
        }

        return $query->get()
            ->mapWithKeys(fn (Model $record): array => [$record->getKey() => $this->getRecordLabel($record)])
            ->all();
    }

    public function getOptionLabel(mixed $key): ?string
    {
        $record = $this->newQuery()->whereKey($key)->first();

        return $record === null ? null : $this->getRecordLabel($record);
    }

    /**
     * @return Builder<Model>
     */
    private function newQuery(): Builder
    {
        $query = (new $this->model)->newQuery();

        return $this->modifyOptionsQueryUsing === null ? $query : (($this->modifyOptionsQueryUsing)($query) ?? $query);
    }

    private function getRecordLabel(Model $record): string
    {
        return $this->optionLabelFromRecordUsing === null
            ? (string) $record->getAttribute($this->getTitleAttribute())
            : (string) ($this->optionLabelFromRecordUsing)($record);
    }

    private function getTitleAttribute(): string
    {
        return $this->titleAttribute ?? throw new FormDefinitionException("The morph type [{$this->model}] needs a titleAttribute().");
    }
}
