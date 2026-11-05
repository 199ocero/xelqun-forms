<?php

namespace Xelqun\Forms\State;

use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Support\StatePath;

/**
 * Writes form state inside server closures such as afterStateUpdated hooks.
 * Every write is sent back to the browser in the update patch.
 *
 * A write lasts for the current request. The browser shows the value, but on
 * later requests a disabled or hidden field goes back to its server value,
 * because the server can't tell the browser echoing this write from tampering.
 * Closures should derive such values from fields the user can change.
 *
 * @api
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
