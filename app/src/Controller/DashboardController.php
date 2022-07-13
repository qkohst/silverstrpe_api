<?php

use SilverStripe\Assets\Upload;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DB;

class DashboardController extends PageController
{
    public function index(HTTPRequest $request)
    {
        $countWarna = count(Warna::get()->where('Deleted = 0'));
        $contProduct = count(Product::get()->where('Deleted = 0'));

        $response = [
            "status" => [
                "code" => 200,
                "description" => "OK",
                "message" => [
                    "Dashboard Data"
                ],
            ],
            "data" => [
                "JumlahWarna" => $countWarna,
                "JumlahProduct" => $contProduct
            ]
        ];

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }
}
