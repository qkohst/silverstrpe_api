<?php

use SilverStripe\ORM\DataObject;

class WarnaProduct extends DataObject
{
    private static $has_one = [
        'Warna' => Warna::class,
        'Product' => Product::class,
    ];

    private static $has_many = [
        'HargaProduct' => HargaProduct::class,
        'JumlahProduct' => JumlahProduct::class,
    ];
}
