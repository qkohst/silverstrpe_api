<?php

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DB;

class PembelianProductController extends ProductController
{
    private static $allowed_actions = [
        'buy',
        'getNewKode',
        'graphic'
    ];

    public function buy(HTTPRequest $request)
    {
        $values = json_decode($_REQUEST['data']);

        $messageError = [];
        // Validate Parameter User ID
        if (!isset($_REQUEST['userID'])) {
            array_push($messageError, 'userID tidak boleh kosong');
        } elseif (isset($_REQUEST['userID'])) {
            // Validate User Exist 
            $user = User::get()->byID($_REQUEST['userID']);
            if (!isset($user->ID)) {
                array_push($messageError, 'User dengan ID ' . $_REQUEST['userID'] . ' tidak ditemukan');
            }
        }

        // Validate  WarnaProductID Exist & Stok
        foreach ($values as $value) {
            $warnaProduct = WarnaProduct::get()->byID($value->WarnaProductID);
            if (!isset($warnaProduct->ID)) {
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
                $jumlahProduct->Jumlah = ($value->JumlahBeli * -1);
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
        $pembelianTerakhir = Pembelian::get()->where("DATE_FORMAT(Created, '%Y%m') = " . date("Ym"))->last();

        if (!isset($pembelianTerakhir->ID)) {
            $nomorUrut = 1;
        } else {
            $maxKode = $pembelianTerakhir->Kode;
            $nomorUrut = (int) substr($maxKode, 11, 5);
            $nomorUrut++;
        }

        $newKode = 'PE/' . date("m/Y/") . sprintf("%05s", $nomorUrut);
        return $newKode;
    }

    public function graphic(HTTPRequest $request)
    {
        // Check Parameter Request 
        if (isset($_REQUEST['tahun'])) {
            $tahun = $_REQUEST['tahun'];
        } else {
            $tahun = date('Y');
        }
        $dataPenjualan = Pembelian::get();

        if (!isset($_REQUEST['bulan'])) {
            // Graphic berdasarkan tahun 
            $sql = "SELECT COUNT(ID) as jumlah, DATE_FORMAT(Created, '%M') AS bulan
                    FROM Pembelian 
                    WHERE DATE_FORMAT(Created, '%Y') = " . $tahun . "
                    GROUP BY YEAR(Created), MONTH(Created)";

            $dataPenjualan = DB::query($sql);

            $result = [];
            foreach ($dataPenjualan as $penjualan) {
                $result[] = [
                    'Bulan' => $penjualan['bulan'],
                    'TotalPenjualan' => $penjualan['jumlah']
                ];
            }

            $response = [
                "status" => [
                    "code" => 200,
                    "description" => "OK",
                    "message" => [
                        'Grafik penjualan tahun ' . $tahun
                    ]
                ],
                "data" => [
                    "tahun" => $tahun,
                    "result" => $result
                ]
            ];
        } elseif (isset($_REQUEST['bulan'])) {
            // Graphic berdasarkan bulan
            $bulan = $_REQUEST['bulan'];

            $sql = "SELECT COUNT(ID) as jumlah, DATE_FORMAT(Created, '%d') AS tanggal
            FROM Pembelian 
            WHERE DATE_FORMAT(Created, '%Y') = " . $tahun . " 
            AND DATE_FORMAT(Created, '%m') = " . $bulan . " 
            GROUP BY MONTH(Created), DAY(Created)";

            $dataPenjualan = DB::query($sql);

            $result = [];
            foreach ($dataPenjualan as $penjualan) {
                $result[] = [
                    'Tanggal' => $penjualan['tanggal'],
                    'TotalPenjualan' => $penjualan['jumlah']
                ];
            }

            $response = [
                "status" => [
                    "code" => 200,
                    "description" => "OK",
                    "message" => [
                        'Grafik penjualan tahun ' . $tahun . ' bulan ' . $bulan
                    ]
                ],
                "data" => [
                    "bulan" => $bulan,
                    "result" => $result
                ]
            ];
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }
}
