<?php

use SilverStripe\ORM\DataObject;

class User extends DataObject
{
    private static $db = [
        'NamaLengkap' => 'Varchar(45)',
        'Email' => 'Varchar(45)',
        'Password' => 'Varchar',
    ];
}
