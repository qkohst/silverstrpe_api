<?php

use SilverStripe\ORM\DataObject;

class Warna extends DataObject
{
    private static $db = [
        'NamaWarna' => 'Varchar(20)',
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
    ];
}
