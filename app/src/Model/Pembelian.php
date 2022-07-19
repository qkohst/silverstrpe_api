<?php

use SilverStripe\ORM\DataObject;

class Pembelian extends DataObject
{
    private static $db = [
        'Kode' => 'Varchar(20)',
    ];

    private static $has_one = [
        'User' => User::class,
    ];

    private static $has_many = [
        'PembelianProduct' => PembelianProduct::class,
    ];
}
