<?php

use SilverStripe\ORM\DataObject;

class HargaProduct extends DataObject
{
    private static $db = [
        'Harga' => 'Double'
    ];

    private static $has_one = [
        'WarnaProduct' => WarnaProduct::class,
    ];
}
