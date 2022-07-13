<?php

use SilverStripe\Assets\Upload;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\PaginatedList;

class ProductController extends PageController
{
    private static $allowed_actions = [
        'store',
        'show',
        'update',
        'delete',
        'showStokHarga',
        'updateStokHarga',
        'historyStok',
        'historyHarga'
    ];

    public function index(HTTPRequest $request)
    {
        $dataArray = array();

        $product = Product::get()->where('Deleted = 0')->limit(10);
        $dataProduct =  new PaginatedList($product, $this->getRequest());

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
        $GambarProduct = (isset($_FILES['GambarProduct'])) ? $_FILES['GambarProduct'] : '';

        // Count data 
        $CountWarna = 0;
        $CountJumlah = 0;
        $CountHarga = 0;
        if (isset($_REQUEST['WarnaProduct'])) {
            $CountWarna = count($_REQUEST['WarnaProduct']);
        }
        if (isset($_REQUEST['Jumlah'])) {
            $CountJumlah = count($_REQUEST['Jumlah']);
        }
        if (isset($_REQUEST['Harga'])) {
            $CountHarga = count($_REQUEST['Harga']);
        }

        if (trim($NamaProduct) == null || trim($DeskripsiProduct) == null || $CountWarna == 0 || count($GambarProduct['name']) < 2) {
            $message = [];
            if (trim($NamaProduct) == null) {
                array_push($message, "Nama product tidak boleh kosong");
            }
            if (trim($DeskripsiProduct) == null) {
                array_push($message, "Deskripsi product tidak boleh kosong");
            }
            if (count($GambarProduct['name']) < 2) {
                array_push($message, "Gambar product minimal berisi 2 gambar");
            }
            if ($CountWarna == 0) {
                array_push($message, "Warna product tidak boleh kosong");
            }
            $response = [
                "status" => [
                    "code" => 422,
                    "description" => "Unprocessable Entity",
                    "message" => $message
                ]
            ];
        } elseif ($CountWarna == $CountJumlah && $CountWarna == $CountHarga) {
            // Validate data warna valid ?
            $messageWarna = [];
            for ($i = 0; $i < $CountWarna; $i++) {
                $checkWarna = Warna::get()->where('Deleted = 0 AND ID = ' . $_REQUEST['WarnaProduct'][$i])->first();
                if (is_null($checkWarna)) {
                    array_push($messageWarna, 'ID Warna index ke ' . $i . ' tidak ditemukan');
                }
            }
            if (count($messageWarna) != 0) {
                $response = [
                    "status" => [
                        "code" => 422,
                        "description" => "Unprocessable Entity",
                        "message" => $messageWarna
                    ]
                ];
            } else {
                // Save Data Product
                $product = Product::create();
                $product->NamaProduct = Convert::raw2sql($_REQUEST['NamaProduct']);
                $product->DeskripsiProduct = $_REQUEST['DeskripsiProduct'];
                $product->Status = 1;
                $product->write();

                // Upload gambar

                $images = [];
                for ($j = 0; $j < count($GambarProduct['name']); $j++) {
                    array_push($images, [
                        'name' => $GambarProduct['name'][$j],
                        'type' => $GambarProduct['type'][$j],
                        'tmp_name' => $GambarProduct['tmp_name'][$j],
                        'error' => $GambarProduct['error'][$j],
                        'size' => $GambarProduct['size'][$j],
                    ]);
                }

                foreach ($images as $img => $file) {
                    if ($file['name'] != '') {
                        $name = $file['name'];
                        $type = $file['type'];
                        $type = explode('/', $type);
                        $type = end($type);
                        if ($type) {
                            $file['name'] = $product->ID . '-' . $img . '-' . date('Y-m-d-H-i-s') . '.' . $type;
                        }

                        $upload = new Upload();
                        $gambarProduct = new Gambar();
                        $gambarProduct->NamaGambar = $product->ID . '-' . $img . '-' . date('Y-m-d-H-i-s') . '.' . $type;
                        $gambarProduct->ProductID = $product->ID;

                        $upload->loadIntoFile($file, $gambarProduct, 'GambarProduct');
                        $gambarProduct->write();
                    }
                }

                // Save Warna Product, Jumlah Product, & Harga
                for ($i = 0; $i < count($_REQUEST['WarnaProduct']); $i++) {

                    // Warna Product 
                    $warnaProduct = WarnaProduct::create();
                    $warnaProduct->WarnaID = Convert::raw2sql($_REQUEST['WarnaProduct'][$i]);
                    $warnaProduct->ProductID = $product->ID;
                    $warnaProduct->write();

                    // Jumlah Product
                    $jumlahProduct = JumlahProduct::create();
                    $jumlahProduct->Jumlah = Convert::raw2sql($_REQUEST['Jumlah'][$i]);
                    $jumlahProduct->WarnaProductID = $warnaProduct->ID;
                    $jumlahProduct->write();

                    // Harga Product
                    $hargaProduct = HargaProduct::create();
                    $hargaProduct->Harga = str_replace(".", "", Convert::raw2sql($_REQUEST['Harga'][$i]));
                    $hargaProduct->WarnaProductID = $warnaProduct->ID;
                    $hargaProduct->write();
                }

                $response = [
                    "status" => [
                        "code" => 200,
                        "description" => "OK",
                        "message" => [
                            "Berhasil disimpan"
                        ]
                    ],
                ];
            }
        } else {
            // Validate Warna, Jumlah & Harga Sama  
            $message = [];
            for ($i = 0; $i < $CountWarna; $i++) {
                if (!isset($_REQUEST['Jumlah'][$i])) {
                    array_push($message, "Jumlah pada warna index ke " . $i . " tidak ditemukan");
                }
                if (!isset($_REQUEST['Harga'][$i])) {
                    array_push($message, "Harga pada warna index ke " . $i . " tidak ditemukan");
                }
            }
            $response = [
                "status" => [
                    "code" => 422,
                    "description" => "Unprocessable Entity",
                    "message" => $message
                ]
            ];
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
                $hargaAktif = HargaProduct::get()->where("WarnaProductID = " . $warnaProduct->ID . " AND TglMulaiBerlaku <= '" . date("Y-m-d H:i:s") . "'")->last();

                $temparr = array();
                $temparr['ID'] = $warnaProduct->ID;
                $temparr['Warna'] = $warnaProduct->Warna->NamaWarna;
                $temparr['Stok'] = $stok->Jumlah;
                $temparr['Harga'] = $hargaAktif->Harga;
                $temparr['TglMulaiBerlaku'] = $hargaAktif->TglMulaiBerlaku;

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

    public function showStokHarga(HTTPRequest $request)
    {
        $id = $request->params()["ID"];
        $warnaProduct = WarnaProduct::get()->byID($id);
        // Validate data exist
        if (is_null($warnaProduct)) {
            $response = [
                "status" => [
                    "code" => 404,
                    "description" => "Not Found",
                    "message" => [
                        "Warna Product dengan ID " . $id . " tidak ditemukan"
                    ]
                ]
            ];
        } else {

            $jumlahProductTerakhir = JumlahProduct::get()->where('WarnaProductID = ' . $warnaProduct->ID)->last();

            $hargaAktif = HargaProduct::get()->where("WarnaProductID = " . $warnaProduct->ID . " AND TglMulaiBerlaku <= '" . date("Y-m-d H:i:s") . "'")->last();

            $response = [
                "status" => [
                    "code" => 200,
                    "description" => "OK",
                    "message" => [
                        "Detail Stok & Harga"
                    ]
                ],
                "data" => [
                    "ID" => "a",
                    "NamaProduct" => $warnaProduct->Product()->NamaProduct,
                    "WarnaProduct" => $warnaProduct->Warna()->NamaWarna,
                    "JumlahStok" => $jumlahProductTerakhir->Jumlah,
                    "Harga" => $hargaAktif->Harga,
                ]
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
            $TglHargaMulaiBerlaku = (isset($_REQUEST['TglHargaMulaiBerlaku'])) ? $_REQUEST['TglHargaMulaiBerlaku'] : '';

            if (trim($Jumlah) == null || trim($Harga) == null || trim($TglHargaMulaiBerlaku) == null) {
                $message = [];
                if (trim($Jumlah) == null) {
                    array_push($message, "Jumlah tidak boleh kosong");
                }
                if (trim($Harga) == null) {
                    array_push($message, "Harga tidak boleh kosong");
                }
                if (trim($TglHargaMulaiBerlaku) == null) {
                    array_push($message, "Tgl Harga Mulai Berlaku tidak boleh kosong");
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
                        $hargaProduct->TglMulaiBerlaku = Convert::raw2sql($_REQUEST['TglHargaMulaiBerlaku']);
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

    public function historyStok(HTTPRequest $request)
    {
        // Validate Required Parameter
        if (!isset($_REQUEST['ProductID']) || !isset($_REQUEST['WarnaProductID'])) {
            $message = [];
            if (!isset($_REQUEST['ProductID'])) {
                array_push($message, 'Parameter ProductID tidak ditemukan.');
            }
            if (!isset($_REQUEST['WarnaProductID'])) {
                array_push($message, 'Parameter WarnaProductID tidak ditemukan.');
            }

            $response = [
                "status" => [
                    "code" => 422,
                    "description" => "Unprocessable Entity",
                    "message" => $message
                ]
            ];
        } else {
            $ProductID = $_REQUEST['ProductID'];
            $WarnaProductID = $_REQUEST['WarnaProductID'];

            $product = Product::get()->byID($ProductID);
            $warnaProduct = WarnaProduct::get()->byID($WarnaProductID);

            $dataStokArray = array();
            $dataHistoryStok = JumlahProduct::get()->where('WarnaProductID = ' . $WarnaProductID)->sort('Created', 'DESC');

            // Validate Data ParameterValid ?
            if (is_null($product) || is_null($warnaProduct) || is_null($dataHistoryStok)) {
                $message = [];
                if (is_null($product)) {
                    array_push($message, 'Product dengan ID ' . $ProductID . ' tidak ditemukan');
                }
                if (is_null($warnaProduct)) {
                    array_push($message, 'Warna Product dengan ID ' . $WarnaProductID . ' tidak ditemukan');
                }
                if (is_null($dataHistoryStok)) {
                    array_push($message, 'Data history stok dengan WarnaProductID ' . $WarnaProductID . ' tidak ditemukan');
                }
                $response = [
                    "status" => [
                        "code" => 404,
                        "description" => "Not Found",
                        "message" => $message
                    ]
                ];
            } else {
                foreach ($dataHistoryStok as $stok) {
                    $temparr = array();
                    $temparr['Tanggal'] = $stok->Created;
                    $temparr['JumlahStok'] = $stok->Jumlah;
                    $dataStokArray[] = $temparr;
                }


                $response = [
                    "status" => [
                        "code" => 200,
                        "description" => "OK",
                        "message" => [
                            "Data History Stok Product"
                        ]
                    ],
                    "data" => [
                        "product" => [
                            "ID" => $product->ID,
                            "NamaProduct" => $product->NamaProduct,
                            "WarnaProduct" => $warnaProduct->Warna()->NamaWarna,
                        ],
                        "historyStok" => $dataStokArray
                    ]
                ];
            }
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function historyHarga(HTTPRequest $request)
    {
        // Validate Required Parameter
        if (!isset($_REQUEST['ProductID']) || !isset($_REQUEST['WarnaProductID'])) {
            $message = [];
            if (!isset($_REQUEST['ProductID'])) {
                array_push($message, 'Parameter ProductID tidak ditemukan.');
            }
            if (!isset($_REQUEST['WarnaProductID'])) {
                array_push($message, 'Parameter WarnaProductID tidak ditemukan.');
            }

            $response = [
                "status" => [
                    "code" => 422,
                    "description" => "Unprocessable Entity",
                    "message" => $message
                ]
            ];
        } else {
            $ProductID = $_REQUEST['ProductID'];
            $WarnaProductID = $_REQUEST['WarnaProductID'];

            $product = Product::get()->byID($ProductID);
            $warnaProduct = WarnaProduct::get()->byID($WarnaProductID);

            $dataHargaArray = array();
            $dataHistoryHarga = HargaProduct::get()->where('WarnaProductID = ' . $WarnaProductID)->sort('Created', 'DESC');
            // Validate Data ParameterValid ?
            if (is_null($product) || is_null($warnaProduct) || is_null($dataHistoryHarga)) {
                $message = [];
                if (is_null($product)) {
                    array_push($message, 'Product dengan ID ' . $ProductID . ' tidak ditemukan');
                }
                if (is_null($warnaProduct)) {
                    array_push($message, 'Warna Product dengan ID ' . $WarnaProductID . ' tidak ditemukan');
                }
                if (is_null($dataHistoryHarga)) {
                    array_push($message, 'Data history harga dengan WarnaProductID ' . $WarnaProductID . ' tidak ditemukan');
                }
                $response = [
                    "status" => [
                        "code" => 404,
                        "description" => "Not Found",
                        "message" => $message
                    ]
                ];
            } else {
                $hargaAktif = HargaProduct::get()->where("WarnaProductID = " . $warnaProduct->ID . " AND TglMulaiBerlaku <= '" . date("Y-m-d H:i:s") . "'")->last();

                foreach ($dataHistoryHarga as $harga) {
                    $temparr = array();
                    $temparr['Harga'] = $harga->Harga;
                    if ($hargaAktif->ID == $harga->ID) {
                        $temparr['Status'] = "Aktif";
                    } else {
                        $temparr['Status'] = "Non Aktif";
                    }
                    $temparr['TglMulaiBerlaku'] = $harga->TglMulaiBerlaku;

                    $dataHargaArray[] = $temparr;
                }

                $response = [
                    "status" => [
                        "code" => 200,
                        "description" => "OK",
                        "message" => [
                            "Data History Harga Product"
                        ]
                    ],
                    "data" => [
                        "product" => [
                            "ID" => $product->ID,
                            "NamaProduct" => $product->NamaProduct,
                            "WarnaProduct" => $warnaProduct->Warna()->NamaWarna,
                        ],
                        "historyHarga" => $dataHargaArray
                    ]
                ];
            }
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }
}
