<?php

namespace Xelqun\Forms\Components;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\Rule;
use LogicException;
use Xelqun\Forms\Components\Concerns\HasOptions;
use Xelqun\Forms\Contracts\HasSearchResults;
use Xelqun\Forms\Contracts\PersistsItself;
use Xelqun\Forms\Exceptions\FormDefinitionException;

/**
 * @api
 */
class Select extends Field implements HasSearchResults, PersistsItself
{
    use HasOptions {
        getOptions as getStaticOptions;
    }

    protected bool $isSearchable = false;

    protected bool $isMultiple = false;

    protected ?bool $preload = null;

    protected ?Closure $searchResultsUsing = null;

    protected ?Closure $optionLabelUsing = null;

    protected ?string $relationship = null;

    protected ?string $titleAttribute = null;

    protected ?Closure $modifyRelationshipQueryUsing = null;

    protected ?Closure $optionLabelFromRecordUsing = null;

    /** @var array<string, mixed>|Closure */
    protected array|Closure $pivotData = [];

    protected int $searchLimit = 50;

    protected int $searchDebounce = 250;

    public function getType(): string
    {
        return 'select';
    }

    /**
     * Filter options while typing. Options load from the server when
     * getSearchResultsUsing() is set, or for relationships unless preload() is used.
     */
    public function searchable(bool $condition = true): static
    {
        $this->isSearchable = $condition;

        return $this;
    }

    public function multiple(bool $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function preload(bool $condition = true): static
    {
        $this->preload = $condition;

        return $this;
    }

    public function getSearchResultsUsing(Closure $callback): static
    {
        $this->searchResultsUsing = $callback;
        $this->isSearchable = true;

        return $this;
    }

    public function getOptionLabelUsing(Closure $callback): static
    {
        $this->optionLabelUsing = $callback;

        return $this;
    }

    /**
     * Load options from a relationship (defaults to the field name). A BelongsTo
     * stores the key in this field's column. A BelongsToMany needs multiple() and
     * attaches and detaches records.
     *
     * The query decides what can be chosen, and a BelongsToMany never detaches
     * a record outside it.
     */
    public function relationship(?string $name = null, ?string $titleAttribute = null, ?Closure $modifyQueryUsing = null): static
    {
        $this->relationship = $name ?? $this->name;
        $this->titleAttribute = $titleAttribute ?? throw new FormDefinitionException("The select [{$this->name}] needs a titleAttribute.");
        $this->modifyRelationshipQueryUsing = $modifyQueryUsing;

        return $this;
    }

    /**
     * Build each option's label from its record, for labels that need more than
     * one column. Searching still looks at the title attribute.
     */
    public function getOptionLabelFromRecordUsing(Closure $callback): static
    {
        $this->optionLabelFromRecordUsing = $callback;

        return $this;
    }

    /**
     * Extra pivot columns for records this field attaches. Records that are
     * already attached keep the values they have.
     *
     * @param  array<string, mixed>|Closure  $data
     */
    public function pivotData(array|Closure $data): static
    {
        $this->pivotData = $data;

        return $this;
    }

    public function shouldPersistItself(): bool
    {
        return $this->relationship !== null && $this->getRelationshipInstance() instanceof BelongsToMany;
    }

    /**
     * Attach and detach by difference rather than with sync(), which would also
     * detach records outside this field's query.
     */
    public function persist(Model $record, mixed $state): void
    {
        /** @var BelongsToMany<Model, Model> $relationship */
        $relationship = $record->{$this->relationship}();
        $selected = array_unique((array) $state);

        $relationship->detach(array_values(array_diff($this->getAttachedKeys($record), $selected)));
        $relationship->attach(
            array_values(array_diff($selected, $this->getAllAttachedKeys($record))),
            $this->evaluate($this->pivotData, ['record' => $record]),
        );
    }

    /**
     * @param  Model|array<string, mixed>|null  $source
     */
    public function fillState(Model|array|null $source): void
    {
        if ($source instanceof Model && $source->exists && $this->shouldPersistItself()) {
            $this->getForm()->setStateValue($this->getStatePath(), $this->getAttachedKeys($source));

            return;
        }

        parent::fillState($source);
    }

    /**
     * @return array<int, int|string>
     */
    private function getAllAttachedKeys(Model $record): array
    {
        /** @var BelongsToMany<Model, Model> $relationship */
        $relationship = $record->{$this->relationship}();

        return $relationship->pluck($relationship->getRelated()->qualifyColumn($this->getRelatedKeyName()))->all();
    }

    /**
     * The attached keys this field can see, as the database gives them, because
     * the browser matches them against option values by type.
     *
     * @return array<int, int|string>
     */
    private function getAttachedKeys(Model $record): array
    {
        $keyName = $this->getRelatedKeyName();

        return $this->getRelationshipQuery()
            ->whereIn($keyName, $this->getAllAttachedKeys($record))
            ->pluck($keyName)
            ->all();
    }

    /**
     * Milliseconds to wait after the last keystroke before searching the server.
     */
    public function searchDebounce(int $milliseconds): static
    {
        $this->searchDebounce = $milliseconds;

        return $this;
    }

    public function searchLimit(int $limit): static
    {
        $this->searchLimit = $limit;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }

    public function isSearchable(): bool
    {
        return $this->isSearchable;
    }

    public function isAsync(): bool
    {
        if (! $this->isSearchable) {
            return false;
        }

        return $this->searchResultsUsing !== null
            || ($this->relationship !== null && $this->preload !== true);
    }

    /**
     * @return array<array-key, string>
     */
    public function getOptions(): array
    {
        if ($this->relationship !== null && $this->options === null) {
            return $this->getRelationshipLabels($this->getRelationshipQuery());
        }

        return $this->getStaticOptions();
    }

    /**
     * @return array<int, array{value: int|string, label: string}>
     */
    public function getSearchResults(string $search): array
    {
        if ($this->searchResultsUsing !== null) {
            $results = $this->evaluate($this->searchResultsUsing, ['search' => $search]);
            $results = $results instanceof Arrayable ? $results->toArray() : $results;

            return $this->toOptionList(array_map(fn (mixed $label): string => (string) $label, is_array($results) ? $results : []));
        }

        if ($this->relationship !== null) {
            $query = $this->getRelationshipQuery();
            $query->where($this->titleAttribute, 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%');

            return $this->toOptionList($this->getRelationshipLabels($query->limit($this->searchLimit)));
        }

        $needle = mb_strtolower($search);

        return $this->toOptionList(array_filter(
            $this->getOptions(),
            fn (string $label): bool => str_contains(mb_strtolower($label), $needle),
        ));
    }

    /**
     * Labels for the currently selected values of an async select.
     *
     * @return array<array-key, string>
     */
    public function getSelectedOptions(): array
    {
        $values = array_filter(
            (array) $this->getState(),
            fn (mixed $value): bool => (is_string($value) || is_int($value)) && $value !== '',
        );

        if ($values === []) {
            return [];
        }

        if ($this->optionLabelUsing !== null) {
            $labels = [];

            foreach ($values as $value) {
                $label = $this->evaluate($this->optionLabelUsing, ['value' => $value]);

                if ($label !== null) {
                    $labels[$value] = (string) $label;
                }
            }

            return $labels;
        }

        if ($this->relationship !== null) {
            return $this->getRelationshipLabels($this->getRelationshipQuery()->whereIn($this->getRelatedKeyName(), $values));
        }

        return array_intersect_key($this->getOptions(), array_flip(array_map('strval', $values)));
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<array-key, string>
     */
    private function getRelationshipLabels(Builder $query): array
    {
        $keyName = $this->getRelatedKeyName();

        if ($this->optionLabelFromRecordUsing === null) {
            return $query->pluck($this->titleAttribute, $keyName)->map(fn (mixed $label): string => (string) $label)->all();
        }

        return $query->get()
            ->mapWithKeys(fn (Model $record): array => [
                $record->getAttribute($keyName) => (string) $this->evaluate($this->optionLabelFromRecordUsing, ['record' => $record]),
            ])
            ->all();
    }

    /**
     * @return Builder<Model>
     */
    protected function getRelationshipQuery(): Builder
    {
        $relationship = $this->getRelationshipInstance();
        $query = $relationship->getRelated()->newQuery();

        if ($this->modifyRelationshipQueryUsing !== null) {
            $query = $this->evaluate($this->modifyRelationshipQueryUsing, ['query' => $query]) ?? $query;
        }

        return $query->orderBy($this->titleAttribute);
    }

    /**
     * @return BelongsTo<Model, Model>|BelongsToMany<Model, Model>
     */
    protected function getRelationshipInstance(): BelongsTo|BelongsToMany
    {
        $model = $this->getForm()->getModelInstanceFor($this);
        $relationship = $model->{$this->relationship}();

        if (! ($relationship instanceof BelongsTo || $relationship instanceof BelongsToMany) || $relationship instanceof MorphTo) {
            throw new LogicException("Select relationship [{$this->relationship}] must be a BelongsTo or BelongsToMany relationship.");
        }

        if ($relationship instanceof BelongsToMany && ! $this->isMultiple) {
            throw new LogicException("Select [{$this->name}] needs multiple() to use the BelongsToMany relationship [{$this->relationship}].");
        }

        return $relationship;
    }

    protected function getRelatedKeyName(): string
    {
        $relationship = $this->getRelationshipInstance();

        return $relationship instanceof BelongsToMany ? $relationship->getRelatedKeyName() : $relationship->getOwnerKeyName();
    }

    protected function getTypeRules(): array
    {
        if ($this->relationship !== null) {
            $exists = function (string $attribute, mixed $value, Closure $fail): void {
                $items = array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : '', (array) $value);
                $keyName = $this->getRelatedKeyName();
                $known = $this->getRelationshipQuery()->whereIn($keyName, $items)->pluck($keyName)->map(strval(...))->all();

                if (array_diff($items, $known) !== []) {
                    $fail($this->getFailureMessage('exists'));
                }
            };

            return $this->isMultiple ? ['array', $exists] : [$exists];
        }

        if ($this->isAsync()) {
            $validate = function (string $attribute, mixed $value, Closure $fail): void {
                $known = $this->getSelectedOptions();

                foreach ((array) $value as $item) {
                    if ((! is_string($item) && ! is_int($item)) || ! array_key_exists($item, $known)) {
                        $fail($this->getFailureMessage('in'));
                    }
                }
            };

            return $this->isMultiple ? ['array', $validate] : [$validate];
        }

        $allowed = array_map('strval', array_keys($this->getOptions()));

        if ($this->isMultiple) {
            return ['array', $this->eachRule(Rule::in($allowed))];
        }

        return [Rule::in($allowed)];
    }

    private function eachRule(mixed $rule): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($rule): void {
            $validator = validator(['items' => (array) $value], ['items.*' => [$rule]]);

            if ($validator->fails()) {
                $fail($this->getFailureMessage('in'));
            }
        };
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        $value = parent::formatStateFromRecord($value);

        return $this->isMultiple ? array_values((array) ($value ?? [])) : $value;
    }

    public function getDefaultState(): mixed
    {
        $default = parent::getDefaultState();

        return $this->isMultiple ? array_values((array) ($default ?? [])) : $default;
    }

    protected function getProps(): array
    {
        $isAsync = $this->isAsync();

        return [
            'options' => $this->toOptionList($isAsync ? $this->getSelectedOptions() : $this->getOptions()),
            'searchable' => $this->isSearchable,
            'multiple' => $this->isMultiple,
            'async' => $isAsync,
            'searchDebounce' => $this->searchDebounce,
        ];
    }
}
