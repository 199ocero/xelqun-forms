<?php

namespace Xelqun\Forms\State;

use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Support\StatePath;

/**
 * Writes form state inside server closures such as afterStateUpdated hooks.
 * Every write is sent back to the browser in the update patch.
 */
final class Set
{
    public function __construct(private readonly Component $component) {}

    public function __invoke(string $path, mixed $value): void
    {
        $absolutePath = StatePath::resolve($path, $this->component->getContainer());
        $form = $this->component->getForm();

        $form->setStateValue($absolutePath, $value);
        $form->markAsSet($absolutePath);
    }
}
