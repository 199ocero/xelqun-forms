<?php

namespace Xelqun\Forms\Contracts;

/**
 * A component that only works with a round trip to the server. A form that
 * can't be rebuilt there refuses it, instead of rendering a field that fails.
 *
 * Every Field implements this for live(). Override needsServer() for anything
 * else that calls the server, such as a route of your own.
 *
 * @api
 */
interface NeedsServer
{
    public function needsServer(): bool;
}
