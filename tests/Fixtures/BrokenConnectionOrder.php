<?php

namespace Xelqun\Forms\Tests\Fixtures;

class BrokenConnectionOrder extends Order
{
    protected $connection = 'broken';

    protected $table = 'orders';
}
