<?php

namespace Xelqun\Forms\Tests\Fixtures;

class OtherConnectionOrder extends Order
{
    protected $connection = 'other';

    protected $table = 'orders';
}
