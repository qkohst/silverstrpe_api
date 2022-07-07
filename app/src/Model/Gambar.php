<?php

use SilverStripe\Assets\File;

class Gambar extends File
{
    private static $db = [
        'NamaGambar' => 'Varchar(100)'
    ];

    private static $has_one = [
        'Product' => Product::class,
    ];
}
