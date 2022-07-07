<?php

use SilverStripe\ORM\DataObject;

class JumlahProduct extends DataObject
{
    private static $db = [
        'Jumlah' => 'Int'
    ];

    private static $has_one = [
        'WarnaProduct' => WarnaProduct::class,
    ];
}
