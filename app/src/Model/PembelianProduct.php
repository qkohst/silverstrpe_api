<?php

use PhpParser\Node\Expr\Cast\Double;
use SilverStripe\ORM\DataObject;

class PembelianProduct extends DataObject
{
    private static $db = [
        'JumlahBeli' => 'Int',
        'HargaBeli' => 'Double'
    ];

    private static $has_one = [
        'Pembelian' => Pembelian::class,
        'WarnaProduct' => WarnaProduct::class,
    ];
}
