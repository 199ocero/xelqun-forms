<?php

namespace Xelqun\Forms\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Form;
use Xelqun\Forms\Security\FormToken;

/**
 * The fields of an action's modal. It is a form of its own, so update, upload
 * and search work as usual. Its token names the action inside the form that
 * holds it, so each of those requests goes through the action's checks again.
 *
 * @internal
 */
final class ActionForm extends Form
{
    private Action $action;

    /** @var array<string, mixed> */
    private array $parentData = [];

    /**
     * @param  array<string, mixed>  $parentData
     */
    public static function for(Action $action, array $parentData): self
    {
        $form = new self($action->getForm()->getRecordFor($action));
        $form->action = $action;
        $form->parentData = $parentData;

        return $form->fill();
    }

    public function schema(): array
    {
        return $this->action->getModalComponents();
    }

    public function authorize(?Authenticatable $user, ?Model $record): bool
    {
        return $this->action->canRun();
    }

    public function model(): ?string
    {
        return $this->action->getModel();
    }

    public function getId(): string
    {
        return 'action-'.$this->action->getName();
    }

    /**
     * Two modals may each hold a "file" field with different rules, so an upload
     * made for one must not count for the other.
     */
    public function getUploadScope(): string
    {
        return $this->action->getForm()->getUploadScope().'>'.$this->action->getKeyPattern();
    }

    public function toToken(): FormToken
    {
        return $this->action->getForm()->toToken()->withAction($this->action->getKey(), $this->parentData);
    }

    protected function getFillSource(): Model|array|null
    {
        return $this->action->getFillData() ?? parent::getFillSource();
    }
}
