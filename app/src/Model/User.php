<?php

use SilverStripe\ORM\DataObject;

class User extends DataObject
{
    private static $db = [
        'NamaLengkap' => 'Varchar(45)',
        'Email' => 'Varchar(45)',
        'Password' => 'Varchar',
        'Token' => 'Varchar(45)',
        'Role' => 'Int'
    ];

    // Role 
    // 1 = Admin 
    // 2 = Member

    private static $has_many = [
        'Pembelian' => Pembelian::class,
    ];
}
