<?php

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;

class WarnaController extends PageController
{
    private static $allowed_actions = [
        'getDataWarnaColumn',
        'getDataWarna',
        'doSave',
        'edit',
        'doUpdate',
        'doDelete'
    ];
    public function index(HTTPRequest $request)
    {
        $dataWarna = Warna::get()->where('Deleted = 0')->sort('NamaWarna');
        $data = [
            "status" => "success",
            "message" => "List of Data Warna",
            "data" => $dataWarna,
        ];
        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($data);
    }
}
