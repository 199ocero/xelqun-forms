<?php

namespace Xelqun\Forms\State;

use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Support\StatePath;

/**
 * Reads form state inside closures and records every path it reads.
 *
 * The recorded paths become the component's dependencies, which tell the
 * browser when a server round-trip is needed.
 */
final class Get
{
    public function __construct(private readonly Component $component) {}

    public function __invoke(string $path = ''): mixed
    {
        $absolutePath = $this->resolve($path);
        $this->component->recordDependency($absolutePath);

        return $this->component->getForm()->getStateValue($absolutePath);
    }

    private function resolve(string $path): string
    {
        if ($path !== '') {
            return StatePath::resolve($path, $this->component->getContainer());
        }

        return $this->component instanceof Field
            ? $this->component->getStatePath()
            : $this->component->getContainer();
    }
}
