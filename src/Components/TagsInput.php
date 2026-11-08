<?php

namespace Xelqun\Forms\Components;

use Closure;
use Illuminate\Support\Facades\Validator;

/**
 * Free-form tags. The state is a list of strings, so a model needs an array cast
 * on the column, unless separator() stores them as one string.
 *
 * @api
 */
class TagsInput extends Field
{
    /** @var array<int, string>|Closure */
    protected array|Closure $suggestions = [];

    protected ?string $separator = null;

    /** @var array<int, string> */
    protected array $splitKeys = [];

    protected bool|Closure $isReorderable = false;

    protected string|Closure|null $tagPrefix = null;

    protected string|Closure|null $tagSuffix = null;

    /** @var array<int, mixed> */
    protected array $tagRules = [];

    public function getType(): string
    {
        return 'tags-input';
    }

    /**
     * Tags the browser suggests. Users can still type anything.
     *
     * @param  array<int, string>|Closure  $suggestions
     */
    public function suggestions(array|Closure $suggestions): static
    {
        $this->suggestions = $suggestions;

        return $this;
    }

    /**
     * Store the tags as one string with this between them, not as a list.
     */
    public function separator(string $separator = ','): static
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * Keys that finish a tag, as well as Enter.
     *
     * @param  array<int, string>  $keys  "Tab" or single characters.
     */
    public function splitKeys(array $keys): static
    {
        $this->splitKeys = $keys;

        return $this;
    }

    /**
     * Let users change the order of the tags.
     */
    public function reorderable(bool|Closure $condition = true): static
    {
        $this->isReorderable = $condition;

        return $this;
    }

    /**
     * Text shown before each tag. It isn't stored.
     */
    public function tagPrefix(string|Closure|null $prefix): static
    {
        $this->tagPrefix = $prefix;

        return $this;
    }

    /**
     * Text shown after each tag. It isn't stored.
     */
    public function tagSuffix(string|Closure|null $suffix): static
    {
        $this->tagSuffix = $suffix;

        return $this;
    }

    /**
     * Rules for each tag. rules() checks the list as a whole.
     *
     * @param  string|array<int, mixed>  $rules
     */
    public function nestedRecursiveRules(string|array $rules): static
    {
        $this->tagRules = is_string($rules) ? explode('|', $rules) : $rules;

        return $this;
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        if ($this->separator !== null && is_string($value)) {
            return $value === '' ? [] : explode($this->separator, $value);
        }

        return array_values((array) ($value ?? []));
    }

    public function getDefaultState(): mixed
    {
        return $this->formatStateFromRecord(parent::getDefaultState());
    }

    public function normalizeState(mixed $state): mixed
    {
        if (! is_array($state)) {
            return $state;
        }

        $tags = array_map(fn (mixed $tag): mixed => is_string($tag) ? trim($tag) : $tag, $state);

        return array_values(array_unique(array_filter($tags, fn (mixed $tag): bool => $tag !== '' && $tag !== null), SORT_REGULAR));
    }

    protected function getTypeRules(): array
    {
        return [
            'array',
            function (string $attribute, mixed $value, Closure $fail): void {
                $tags = array_values((array) $value);
                // Each tag is named, so a message says which one, not "tags.1".
                $names = array_map(fn (mixed $tag): string => $this->getValidationAttribute().' "'.(is_scalar($tag) ? $tag : '').'"', $tags);
                $validator = Validator::make(['tags' => $tags], ['tags.*' => ['string', ...$this->tagRules]], attributes: array_combine(array_map(fn (int $index): string => "tags.{$index}", array_keys($tags)), $names));

                if ($validator->fails()) {
                    $fail($validator->errors()->first());
                }
            },
        ];
    }

    public function dehydrateState(mixed $value): mixed
    {
        return $this->separator !== null && is_array($value) ? implode($this->separator, $value) : $value;
    }

    protected function getProps(): array
    {
        return [
            'suggestions' => array_values($this->evaluate($this->suggestions)),
            'splitKeys' => $this->splitKeys,
            ...$this->evaluateConfigured([
                'reorderable' => $this->isReorderable,
                'tagPrefix' => $this->tagPrefix,
                'tagSuffix' => $this->tagSuffix,
            ]),
        ];
    }
}
