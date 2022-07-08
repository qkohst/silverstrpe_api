<?php

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;

class ProductController extends PageController
{
    private static $allowed_actions = [
        'store',
        'show',
        'update',
        'delete',
        'updateStokHarga'
    ];

    public function index(HTTPRequest $request)
    {
        $dataArray = array();

        $dataProduct = Product::get()->where('Deleted = 0');

        foreach ($dataProduct as $product) {
            $gambar = Gambar::get()->where('ProductID = ' . $product->ID)->first();
            $temparr = array();
            $temparr['ID'] = $product->ID;
            $temparr['NamaProduct'] = $product->NamaProduct;
            $temparr['Status'] = $product->Status;
            $temparr['Gambar'] = [
                "NamaGambar" => $gambar->NamaGambar,
                "URL" => "/public/assets/GambarProduct/" . $gambar->NamaGambar,
            ];

            $dataArray[] = $temparr;
        }

        $response = [
            "status" => [
                "code" => 200,
                "description" => "OK",
                "message" => [
                    "List Product"
                ],
            ],
            "data" => $dataArray
        ];

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function store(HTTPRequest $request)
    {
        $NamaProduct = (isset($_REQUEST['NamaProduct'])) ? $_REQUEST['NamaProduct'] : '';
        $DeskripsiProduct = (isset($_REQUEST['DeskripsiProduct'])) ? $_REQUEST['DeskripsiProduct'] : '';

        // $CountGambar = 0;
        // if (hash_file($_FILES['GambarProduct'])) {
        //     $file = $_FILES['GambarProduct'];
        //     $CountGambar = count($file);
        // }

        // BELUM 

        $CountWarna = 0;
        if (isset($_REQUEST['WarnaProduct'][0])) {
            $CountWarna = count($_REQUEST['WarnaProduct']);
        }

        if (trim($NamaProduct) == null || trim($DeskripsiProduct) == null || $CountWarna == 0) {
            $message = [];
            if (trim($NamaProduct) == null) {
                array_push($message, "Nama product tidak boleh kosong");
            }
            if (trim($DeskripsiProduct) == null) {
                array_push($message, "Deskripsi product tidak boleh kosong");
            }
            // if ($CountGambar == 0) {
            //     array_push($message, "Gambar Product tidak boleh kosong");
            // }
            if ($CountWarna == 0) {
                array_push($message, "Silahkan pilih warna product kemudian isikan jumlah & harga");
            }
            $response = [
                "status" => [
                    "code" => 422,
                    "description" => "Unprocessable Entity",
                    "message" => $message
                ]
            ];
        } else {
            // 
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function show(HTTPRequest $request)
    {
        $id = $request->params()["ID"];
        $product = Product::get()->byID($id);

        if (is_null($product)) {
            $response = [
                "status" => [
                    "code" => 404,
                    "description" => "Not Found",
                    "message" => [
                        "Product dengan ID " . $id . " tidak ditemukan"
                    ]
                ]
            ];
        } else {
            $dataGambarArray = array();
            $dataPilihanProductArray = array();

            // Looping Gambar Product
            $dataGambar = Gambar::get()->where('ProductID = ' . $product->ID);
            foreach ($dataGambar as $gambar) {
                $temparr = array();
                $temparr['ID'] = $gambar->ID;
                $temparr['NamaGambar'] = $gambar->NamaGambar;
                $temparr['URL'] = "/public/assets/GambarProduct/" . $gambar->NamaGambar;
                $dataGambarArray[] = $temparr;
            }

            // Looping Warna Product 
            $dataPilihanProduct = WarnaProduct::get()->where('ProductID = ' . $product->ID);
            foreach ($dataPilihanProduct as $warnaProduct) {
                $stok = JumlahProduct::get()->where('WarnaProductID = ' . $warnaProduct->ID)->last();
                $harga = HargaProduct::get()->where('WarnaProductID = ' . $warnaProduct->ID)->last();
                $temparr = array();
                $temparr['ID'] = $warnaProduct->ID;
                $temparr['Warna'] = $warnaProduct->Warna->NamaWarna;
                $temparr['Stok'] = $stok->Jumlah;
                $temparr['Harga'] = $harga->Harga;

                $dataPilihanProductArray[] = $temparr;
            }

            $response = [
                "status" => [
                    "code" => 200,
                    "description" => "OK",
                    "message" => [
                        "Detail Product"
                    ],
                ],
                "data" => [
                    "ID" => $product->ID,
                    "NamaProduct" => $product->NamaProduct,
                    "DeskripsiProduct" => $product->DeskripsiProduct,
                    "Status" => $product->Status,
                    "GambarProduct" => $dataGambarArray,
                    "PilihanProduct" => $dataPilihanProductArray
                ]
            ];
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function update(HTTPRequest $request)
    {
        $id = $request->params()["ID"];
        $product = Product::get()->byID($id);
        // Validation null 
        if (is_null($product)) {
            $response = [
                "status" => [
                    "code" => 404,
                    "description" => "Not Found",
                    "message" => [
                        "Product dengan ID " . $id . " tidak ditemukan"
                    ]
                ]
            ];
        } else {
            $Status = (isset($_REQUEST['Status'])) ? $_REQUEST['Status'] : '';
            $DeskripsiProduct = (isset($_REQUEST['DeskripsiProduct'])) ? $_REQUEST['DeskripsiProduct'] : '';
            if (trim($Status) == null || trim($DeskripsiProduct) == null) {
                $message = [];
                if (trim($Status) == null) {
                    array_push($message, "Silahkan pilih status");
                }
                if (trim($DeskripsiProduct) == null) {
                    array_push($message, 'Deskripsi tidak boleh kosong');
                }
                $response = [
                    "status" => [
                        "code" => 422,
                        "description" => "Unprocessable Entity",
                        "message" => $message
                    ]
                ];
            } else {
                $product->update([
                    'Status' => Convert::raw2sql($Status),
                    'DeskripsiProduct' => Convert::raw2sql($DeskripsiProduct)
                ]);
                $product->write();
                $response = [
                    "status" => [
                        "code" => 200,
                        "description" => "OK",
                        "message" => [
                            "Berhasil diupdate"
                        ]
                    ],
                ];
            }
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function delete(HTTPRequest $request)
    {
        $id = $request->params()["ID"];
        $product = Product::get()->byID($id);
        // Validation null 
        if (is_null($product)) {
            $response = [
                "status" => [
                    "code" => 404,
                    "description" => "Not Found",
                    "message" => [
                        "Product dengan ID " . $id . " tidak ditemukan"
                    ]
                ]
            ];
        } else {
            $product->update([
                'Deleted' => 1
            ]);
            $product->write();

            $response = [
                "status" => [
                    "code" => 200,
                    "description" => "OK",
                    "message" => [
                        "Berhasil dihapus"
                    ]
                ],
            ];
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function updateStokHarga(HTTPRequest $request)
    {
        $id = $request->params()["ID"];
        $warnaProduct = WarnaProduct::get()->byID($id);
        // Validation null 
        if (is_null($warnaProduct)) {
            $response = [
                "status" => [
                    "code" => 404,
                    "description" => "Not Found",
                    "message" => [
                        "Pilihan Product dengan ID " . $id . " tidak ditemukan"
                    ]
                ]
            ];
        } else {
            // Validation required
            $Jumlah = (isset($_REQUEST['Jumlah'])) ? $_REQUEST['Jumlah'] : '';
            $Harga = (isset($_REQUEST['Harga'])) ? $_REQUEST['Harga'] : '';
            if (trim($Jumlah) == null || trim($Harga) == null) {
                $message = [];
                if (trim($Jumlah) == null) {
                    array_push($message, "Jumlah tidak boleh kosong");
                }
                if (trim($Harga) == null) {
                    array_push($message, "Harga tidak boleh kosong");
                }
                $response = [
                    "status" => [
                        "code" => 422,
                        "description" => "Unprocessable Entity",
                        "message" => $message
                    ]
                ];
            } else {
                // Validate data modified
                $hargaTerakhir = HargaProduct::get()->where('WarnaProductID = ' . $id)->last();
                $jumlahterakhir = JumlahProduct::get()->where('WarnaProductID = ' . $id)->last();
                if ($hargaTerakhir->Harga == str_replace(".", "", Convert::raw2sql($_REQUEST['Harga'])) && $jumlahterakhir->Jumlah == Convert::raw2sql($_REQUEST['Jumlah'])) {
                    $response = [
                        "status" => [
                            "code" => 304,
                            "description" => "Not Modified",
                            "message" => [
                                "Tidak ada perubahan data"
                            ]
                        ]
                    ];
                } elseif ($hargaTerakhir->Harga != str_replace(".", "", Convert::raw2sql($_REQUEST['Harga'])) || $jumlahterakhir->Jumlah != Convert::raw2sql($_REQUEST['Jumlah'])) {
                    if ($hargaTerakhir->Harga != str_replace(".", "", Convert::raw2sql($_REQUEST['Harga']))) {
                        $hargaProduct = HargaProduct::create();
                        $hargaProduct->Harga = str_replace(".", "", Convert::raw2sql($_REQUEST['Harga']));
                        $hargaProduct->WarnaProductID = $id;
                        $hargaProduct->write();
                    }
                    if ($jumlahterakhir->Jumlah != Convert::raw2sql($_REQUEST['Jumlah'])) {
                        $jumlahProduct = JumlahProduct::create();
                        $jumlahProduct->Jumlah = Convert::raw2sql($_REQUEST['Jumlah']);
                        $jumlahProduct->WarnaProductID = $id;
                        $jumlahProduct->write();
                    }
                    $response = [
                        "status" => [
                            "code" => 200,
                            "description" => "OK",
                            "message" => [
                                "Berhasil diupdate"
                            ]
                        ],
                    ];
                }
            }
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }
}
