<?php

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;

class PembelianProductController extends ProductController
{
    private static $allowed_actions = [
        'buy',
        'getNewKode'
    ];

    public function buy(HTTPRequest $request)
    {
        // // Count data 
        // $CountProduct = 0;
        // $CountJumlahBeli = 0;
        // if (isset($_REQUEST['WarnaProductID'])) {
        //     $CountProduct = count($_REQUEST['WarnaProductID']);
        // }
        // if (isset($_REQUEST['JumlahBeli'])) {
        //     $CountJumlahBeli = count($_REQUEST['JumlahBeli']);
        // }

        // // Check Validate Parameter 
        // if ($CountProduct == 0 || $CountJumlahBeli == 0 || $CountProduct != $CountJumlahBeli) {
        //     $message = [];
        //     if ($CountProduct == 0) {
        //         array_push($message, 'WarnaProductID tidak boleh kosong');
        //     }
        //     if ($CountJumlahBeli == 0) {
        //         array_push($message, 'JumlahBeli tidak boleh kosong');
        //     }
        //     if ($CountProduct != $CountJumlahBeli) {
        //         array_push($message, 'JumlahBeli dan WarnaProductID yang anda beli tidak valid.');
        //     }

        //     $response = [
        //         "status" => [
        //             "code" => 422,
        //             "description" => "Unprocessable Entity",
        //             "message" => $message
        //         ]
        //     ];
        // } elseif ($CountProduct == $CountJumlahBeli) {
        //     // Validate  WarnaProductID Exist & Stok
        //     $messageError = [];
        //     for ($i = 0; $i < $CountProduct; $i++) {
        //         $stokProduct = JumlahProduct::get()->where('WarnaProductID = ' . $_REQUEST['WarnaProductID'][$i])->last();
        //         if ($stokProduct == null) {
        //             array_push($messageError, 'Product dengan WarnaProductID ' . $_REQUEST['WarnaProductID'][$i] . ' tidak tersedia');
        //         } elseif ($stokProduct->Jumlah < $_REQUEST['JumlahBeli'][$i]) {
        //             array_push($messageError, 'Sisa stok untuk product dengan WarnaProductID ' . $_REQUEST['WarnaProductID'][$i] . ' tidak mencukupi');
        //         }
        //     }
        //     if (count($messageError) != 0) {
        //         $response = [
        //             "status" => [
        //                 "code" => 422,
        //                 "description" => "Unprocessable Entity",
        //                 "message" => $messageError
        //             ]
        //         ];
        //     } elseif (count($messageError) == 0) {
        //         // Save data pembelian
        //         $pembelian = Pembelian::create();
        //         $pembelian->Kode = $this->getNewKode();
        //         $pembelian->UserID = $this->getUserLogin()->ID;
        //         $pembelian->write();

        //         for ($i = 0; $i < $CountProduct; $i++) {
        //             $stokProduct = JumlahProduct::get()->where('WarnaProductID = ' . $_REQUEST['WarnaProductID'][$i])->last();
        //             $hargaProduct = HargaProduct::get()->where("WarnaProductID = " . $_REQUEST['WarnaProductID'][$i] . " AND TglMulaiBerlaku <= '" . date("Y-m-d H:i:s") . "'")->last();

        //             // Save Pembelian Product 
        //             $pembelianProduct = PembelianProduct::create();
        //             $pembelianProduct->PembelianID = $pembelian->ID;
        //             $pembelianProduct->WarnaProductID = $_REQUEST['WarnaProductID'][$i];
        //             $pembelianProduct->JumlahBeli = $_REQUEST['JumlahBeli'][$i];
        //             $pembelianProduct->HargaBeli = $hargaProduct->Harga;
        //             $pembelianProduct->write();

        //             // Update Stok Product 
        //             $jumlahProduct = JumlahProduct::create();
        //             $jumlahProduct->Jumlah = ($stokProduct->Jumlah - $_REQUEST['JumlahBeli'][$i]);
        //             $jumlahProduct->WarnaProductID = $_REQUEST['WarnaProductID'][$i];
        //             $jumlahProduct->write();
        //         }

        //         $response = [
        //             "status" => [
        //                 "code" => 200,
        //                 "description" => "OK",
        //                 "message" => [
        //                     'Berhasil disimpan'
        //                 ]
        //             ]
        //         ];
        //     }
        // }

        $values = json_decode($_REQUEST['data']);
        // Validate  WarnaProductID Exist & Stok

        $messageError = [];
        foreach ($values as $value) {
            $warnaProduct = WarnaProduct::get()->byID($value->WarnaProductID);
            if ($warnaProduct == null) {
                array_push($messageError, 'Product dengan WarnaProductID ' . $value->WarnaProductID . ' tidak tersedia');
            } elseif ($warnaProduct->Stok < $value->JumlahBeli) {
                array_push($messageError, 'Sisa stok untuk product dengan WarnaProductID ' . $value->WarnaProductID . ' tidak mencukupi');
            }
        }

        if (count($messageError) != 0) {
            $response = [
                "status" => [
                    "code" => 422,
                    "description" => "Unprocessable Entity",
                    "message" => $messageError
                ]
            ];
        } elseif (count($messageError) == 0) {
            // Save data pembelian
            $pembelian = Pembelian::create();
            $pembelian->Kode = $this->getNewKode();
            $pembelian->UserID = $_REQUEST['userID'];
            $pembelian->write();

            foreach ($values as $value) {
                $warnaProduct = WarnaProduct::get()->byID($value->WarnaProductID);
                $hargaProduct = HargaProduct::get()->where("WarnaProductID = " . $value->WarnaProductID . " AND TglMulaiBerlaku <= '" . date("Y-m-d H:i:s") . "'")->last();

                // Save Pembelian Product 
                $pembelianProduct = PembelianProduct::create();
                $pembelianProduct->PembelianID = $pembelian->ID;
                $pembelianProduct->WarnaProductID = $value->WarnaProductID;
                $pembelianProduct->JumlahBeli = $value->JumlahBeli;
                $pembelianProduct->HargaBeli = $hargaProduct->Harga;
                $pembelianProduct->write();

                // Save History Stok 
                $jumlahProduct = JumlahProduct::create();
                $jumlahProduct->Jumlah = -$value->JumlahBeli;
                $jumlahProduct->WarnaProductID = $warnaProduct->ID;
                $jumlahProduct->write();

                // Update Stok 
                $warnaProduct->update([
                    'Stok' => ($warnaProduct->Stok - $value->JumlahBeli),
                ]);
                $warnaProduct->write();
            }

            $response = [
                "status" => [
                    "code" => 200,
                    "description" => "OK",
                    "message" => [
                        'Berhasil disimpan'
                    ]
                ]
            ];
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    protected function getNewKode()
    {
        $countPembelianBulanIni = Pembelian::get()->where("DATE_FORMAT(Created, '%Y%m') = " . date("Ym"))->count();
        $newKode = 'PE/' . date("m/Y/0") . ($countPembelianBulanIni + 1);
        return $newKode;
    }
}
