<?php

namespace Xelqun\Forms\Components\Builder;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Layout;

/**
 * One kind of row in a Builder. Its fields live under the row's "data" key.
 *
 * @api
 */
class Block extends Layout
{
    protected string|Closure|null $label = null;

    protected string|Closure|null $icon = null;

    protected int|Closure|null $maxItems = null;

    protected ?string $group = null;

    protected string|Closure|null $preview = null;

    protected bool $showsPreview = false;

    final public function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        return (new static($name))->configure();
    }

    public function getType(): string
    {
        return 'block';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * How many times this block can be used in one builder.
     */
    public function maxItems(int|Closure|null $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    /**
     * What blockPreviews() shows: a Blade view, which gets the row's data as
     * variables, or a closure that gets it as $data and returns HTML. The result
     * reaches the page as it is, so escape what the user typed.
     */
    public function preview(string|Closure|null $preview): static
    {
        $this->preview = $preview;

        return $this;
    }

    /**
     * @internal
     */
    public function showsPreview(bool $condition): static
    {
        $this->showsPreview = $condition;

        return $this;
    }

    /**
     * @internal
     */
    public function inGroup(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getLabel(): string
    {
        return (string) ($this->evaluate($this->label) ?? Str::headline($this->name));
    }

    public function getIcon(): ?string
    {
        return $this->evaluate($this->icon);
    }

    public function getMaxItems(): ?int
    {
        return $this->evaluate($this->maxItems);
    }

    public function getUploadScopePart(): string
    {
        return '|'.$this->name;
    }

    public function getChildSource(Model|array|null $source): Model|array|null
    {
        return is_array($source) ? ($source['data'] ?? []) : null;
    }

    protected function getChildContainer(): string
    {
        return $this->container.'.data';
    }

    protected function getChildContainerPattern(): string
    {
        return $this->containerPattern.'.data';
    }

    /**
     * The template has no row to show, so it has no preview either.
     */
    private function renderPreview(): ?string
    {
        $form = $this->getForm();

        if ($this->preview === null || ! $this->showsPreview || ! Arr::has($form->getState(), $this->container)) {
            return null;
        }

        $path = $this->getChildContainer();
        $fields = [];

        Form::eachField($this->getChildComponents(), function (Field $field) use (&$fields): void {
            $fields[$field->getName()] = true;
        });

        // Only what the block declares: a posted "__env" would replace Blade's own variable.
        $data = array_intersect_key((array) $form->getStateValue($path), $fields);

        $this->recordDependency($path);

        $preview = $this->preview instanceof Closure
            ? $this->evaluate($this->preview, ['data' => $data])
            : view($this->preview, $data)->render();

        return $preview instanceof Htmlable ? $preview->toHtml() : $preview;
    }

    protected function getProps(): array
    {
        return [...parent::getProps(), 'preview' => $this->renderPreview()];
    }
}
