<?php

namespace Xelqun\Actions;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Security\FormToken;

/**
 * Buttons that belong to a page, with no form or table around them. Render one
 * with XqActions.
 *
 * It is a class because a closure can't wait in the browser: every click
 * rebuilds this from its token, finds the action and checks it again. Being a
 * form brings modals, uploads and those tokens with no new code.
 *
 * To work on a record, pass it to make() and declare model(), which is how the
 * token finds it again.
 *
 * @api
 *
 * @template TModel of Model = Model
 *
 * @extends Form<TModel>
 */
abstract class ActionSet extends Form
{
    private bool $hasFilledInModels = false;

    /**
     * @return array<int, Action>
     */
    abstract public function actions(): array;

    public function schema(): array
    {
        $actions = $this->actions();
        $names = array_map(fn (Action $action): string => $action->getName(), $actions);

        if (count($names) !== count(array_unique($names))) {
            throw new FormDefinitionException('The class ['.static::class.'] has two actions with the same name.');
        }

        return $actions;
    }

    /**
     * An action is found by name, because actions() can return another list by
     * the time a click arrives, and a position could then mean another action.
     */
    protected function getRootPosition(Component $component, int $index): string
    {
        return $component instanceof Action ? $component->getName() : parent::getRootPosition($component, $index);
    }

    /**
     * A policy needs the model when there is no record, as in authorize('create').
     * An action can only read its own once it is mounted.
     */
    public function getComponents(): array
    {
        $components = parent::getComponents();

        if (! $this->hasFilledInModels) {
            $this->hasFilledInModels = true;

            foreach ($components as $action) {
                if ($action instanceof Action && $action->getModel() === null) {
                    $action->model($this->model());
                }
            }
        }

        return $components;
    }

    /**
     * Without model(), the token could never find the record again, and every
     * click would be refused. Saying so here means the page fails, not the click.
     */
    public function toToken(): FormToken
    {
        if ($this->record !== null && $this->model() === null) {
            throw new FormDefinitionException('The class ['.static::class.'] must define model() to work on a record.');
        }

        return parent::toToken();
    }
}
