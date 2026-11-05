<?php

namespace Acme\Reviews;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Contracts\PersistsItself;

/**
 * Stars from 1 to max(). With relationship(), they live on a related model.
 */
class Rating extends Field implements PersistsItself
{
    protected int $max = 5;

    protected ?string $relationship = null;

    protected string $column = 'stars';

    public function getType(): string
    {
        return 'acme/rating';
    }

    public function max(int $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function relationship(string $name, string $column = 'stars'): static
    {
        $this->relationship = $name;
        $this->column = $column;

        return $this;
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

        $this->getForm()->setStateValue(
            $this->getStatePath(),
            $source->{$this->relationship}()->value($this->column),
        );
    }

    public function shouldPersistItself(): bool
    {
        return $this->relationship !== null;
    }

    public function persist(Model $record, mixed $state): void
    {
        $relationship = $record->{$this->relationship}();

        if ($state === null) {
            $relationship->delete();

            return;
        }

        $relationship->updateOrCreate([], [$this->column => $state]);
    }

    protected function getTypeRules(): array
    {
        return [
            function (string $attribute, mixed $value, Closure $fail): void {
                if (filter_var($value, FILTER_VALIDATE_INT) === false || $value < 1 || $value > $this->max) {
                    $fail(__('acme-reviews::reviews.rating_range', ['attribute' => $this->getLabel(), 'max' => $this->max]));
                }
            },
        ];
    }

    protected function getProps(): array
    {
        return ['max' => $this->max];
    }
}
