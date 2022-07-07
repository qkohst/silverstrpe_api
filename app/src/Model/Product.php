<?php

use SilverStripe\ORM\DataObject;

class Product extends DataObject
{
    private static $db = [
        'NamaProduct' => 'Varchar(100)',
        'DeskripsiProduct' => 'HTMLText',
        'Status' => 'Boolean',
        'Deleted' => 'Boolean'
    ];

    // Status 
    // 1 = Aktif 
    // 0 = Non Aktif 

    // Deleted 
    // 1 = yes 
    // 0 = no

    private static $has_many = [
        'WarnaProduct' => WarnaProduct::class,
        'Gambar' => Gambar::class,
    ];
}
