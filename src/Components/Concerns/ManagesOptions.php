<?php

namespace Xelqun\Forms\Components\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Xelqun\Actions\Action;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\State\Get;

/**
 * Create or edit an option from beside its select, through a modal.
 *
 * Neither is allowed by default. Create needs the related model's "create"
 * policy, and edit its "update" policy for the selected record, whatever the
 * form itself allows. Change that with createOptionAction() and editOptionAction().
 *
 * @api
 */
trait ManagesOptions
{
    /** @var array<int, Component>|Closure|null */
    protected array|Closure|null $createOptionForm = null;

    /** @var array<int, Component>|Closure|null */
    protected array|Closure|null $editOptionForm = null;

    protected ?Closure $createOptionUsing = null;

    protected ?Closure $updateOptionUsing = null;

    protected ?Closure $fillEditOptionFormUsing = null;

    protected ?Closure $configureCreateOptionAction = null;

    protected ?Closure $configureEditOptionAction = null;

    protected string|Closure|null $createOptionModalHeading = null;

    protected string|Closure|null $editOptionModalHeading = null;

    /** @var array{state: mixed, record: ?Model}|null */
    private ?array $selectedRecord = null;

    /**
     * Show a button that opens these fields, and selects the record they create.
     *
     * @param  array<int, Component>|Closure|null  $components
     */
    public function createOptionForm(array|Closure|null $components): static
    {
        $this->createOptionForm = $components;

        return $this;
    }

    /**
     * Create the record yourself, and return its key. It is needed without a
     * relationship. Check permissions for what it does.
     */
    public function createOptionUsing(?Closure $callback): static
    {
        $this->createOptionUsing = $callback;

        return $this;
    }

    /**
     * Change the button, such as its authorize(). Gets the Action.
     */
    public function createOptionAction(?Closure $callback): static
    {
        $this->configureCreateOptionAction = $callback;

        return $this;
    }

    public function createOptionModalHeading(string|Closure|null $heading): static
    {
        $this->createOptionModalHeading = $heading;

        return $this;
    }

    /**
     * Show a button that edits the selected record. It needs a relationship and
     * a single value. The record is looked up through the relationship's query,
     * so a posted key outside that query opens nothing.
     *
     * @param  array<int, Component>|Closure|null  $components
     */
    public function editOptionForm(array|Closure|null $components): static
    {
        $this->editOptionForm = $components;

        return $this;
    }

    /**
     * Save the changes yourself. It gets $data and $record. Check permissions
     * for what it does.
     */
    public function updateOptionUsing(?Closure $callback): static
    {
        $this->updateOptionUsing = $callback;

        return $this;
    }

    /**
     * Fill the edit form with this instead of the record's values for its fields.
     */
    public function fillEditOptionActionFormUsing(?Closure $callback): static
    {
        $this->fillEditOptionFormUsing = $callback;

        return $this;
    }

    /**
     * Change the button, such as its authorize(). Gets the Action.
     */
    public function editOptionAction(?Closure $callback): static
    {
        $this->configureEditOptionAction = $callback;

        return $this;
    }

    public function editOptionModalHeading(string|Closure|null $heading): static
    {
        $this->editOptionModalHeading = $heading;

        return $this;
    }

    /**
     * The same fields for creating and editing.
     *
     * @param  array<int, Component>|Closure|null  $components
     */
    public function manageOptionForm(array|Closure|null $components): static
    {
        return $this->createOptionForm($components)->editOptionForm($components);
    }

    /**
     * Change both buttons at once. Gets the Action.
     */
    public function manageOptionActions(?Closure $callback): static
    {
        return $this->createOptionAction($callback)->editOptionAction($callback);
    }

    /**
     * Built when the field is placed, because each row needs buttons tied to its
     * own copy of this field.
     *
     * @return array<int, Action>
     */
    protected function makeOptionActions(): array
    {
        return array_values(array_filter([
            $this->createOptionForm === null ? null : $this->makeCreateOptionAction(),
            $this->editOptionForm === null ? null : $this->makeEditOptionAction(),
        ]));
    }

    private function makeCreateOptionAction(): Action
    {
        $action = Action::make('createOption')
            ->label(__('xelqun-forms::messages.select.create_option'))
            ->icon('plus')
            ->iconButton()
            ->model(fn (): ?string => $this->getOptionModel())
            ->authorize('create')
            ->disabled(fn (): bool => $this->isDisabled())
            ->modalHeading($this->createOptionModalHeading)
            ->schema($this->createOptionForm)
            ->action(function (array $data): void {
                $key = $this->createOption($data);

                $this->state($this->isMultiple ? [...(array) $this->getState(), $key] : $key);
                $this->callAfterStateUpdated();
                $this->getForm()->refreshComponent($this);
            });

        return $this->configureCreateOptionAction?->__invoke($action) ?? $action;
    }

    private function makeEditOptionAction(): Action
    {
        $action = Action::make('editOption')
            ->label(__('xelqun-forms::messages.select.edit_option'))
            ->icon('pencil')
            ->iconButton()
            ->hidden(fn (Get $get): bool => $this->isMultiple || blank($get($this->name)) || $this->getSelectedRecord() === null)
            ->record(fn (): ?Model => $this->getSelectedRecord())
            ->model(fn (): ?string => $this->getOptionModel())
            ->authorize('update')
            ->disabled(fn (): bool => $this->isDisabled())
            ->modalHeading($this->editOptionModalHeading)
            ->schema($this->editOptionForm)
            ->action(function (array $data, ?Model $record): void {
                if ($this->updateOptionUsing !== null) {
                    $this->evaluate($this->updateOptionUsing, ['data' => $data, 'record' => $record]);
                } else {
                    $record?->forceFill($data)->save();
                }

                $this->getForm()->refreshComponent($this);
            });

        if ($this->fillEditOptionFormUsing !== null) {
            $action->fillForm(fn (?Model $record): array => $this->evaluate($this->fillEditOptionFormUsing, ['record' => $record]));
        }

        return $this->configureEditOptionAction?->__invoke($action) ?? $action;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createOption(array $data): mixed
    {
        if ($this->createOptionUsing !== null) {
            return $this->evaluate($this->createOptionUsing, ['data' => $data]);
        }

        if ($this->relationship === null) {
            throw new LogicException("Select [{$this->name}] needs createOptionUsing() or a relationship to create an option.");
        }

        $record = $this->getRelationshipInstance()->getRelated()->newInstance()->forceFill($data);
        $record->save();

        return $record->getAttribute($this->getRelatedKeyName());
    }

    /**
     * @return class-string<Model>|null
     */
    private function getOptionModel(): ?string
    {
        return $this->relationship === null ? null : $this->getRelationshipInstance()->getRelated()::class;
    }

    /**
     * The record behind the current value. Going through the relationship's
     * query keeps a posted key from reaching a record this field can't offer.
     * Each check asks for it, so one lookup is kept per value.
     */
    private function getSelectedRecord(): ?Model
    {
        $state = $this->getState();

        if ($this->selectedRecord !== null && $this->selectedRecord['state'] === $state) {
            return $this->selectedRecord['record'];
        }

        $record = $this->relationship === null || blank($state) || is_array($state)
            ? null
            : $this->getRelationshipQuery()->where($this->getRelatedKeyName(), $state)->first();

        $this->selectedRecord = ['state' => $state, 'record' => $record];

        return $record;
    }
}
