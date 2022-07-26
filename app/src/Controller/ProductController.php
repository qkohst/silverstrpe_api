<?php

use SilverStripe\Assets\Upload;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\PaginatedList;

class ProductController extends PageController
{
    private static $allowed_actions = [
        'getHargaAktif',
        'store',
        'show',
        'update',
        'delete',
        'showStokHarga',
        'updateHarga',
        'historyHarga',
        'updateStok',
        'historyStok',
        'search',
        'suggest',
        'filter'
    ];

    private function getHargaAktif($id)
    {
        $hargaAktif = HargaProduct::get()->where("WarnaProductID = " . $id . " AND TglMulaiBerlaku <= '" . date("Y-m-d H:i:s") . "'")->last();
        return $hargaAktif;
    }

    public function index(HTTPRequest $request)
    {
        // Check Parameter Request 

        if (isset($_REQUEST['pages'])) {
            $page = $_REQUEST['pages'];
        } else {
            $page = 1;
        }

        if (isset($_REQUEST['limit'])) {
            $limit = $_REQUEST['limit'];
        } else {
            $limit = 10;
        }
        $offset = ($page - 1) * $limit;

        // 
        $dataArray = array();

        $dataProduct = Product::get()->where('Deleted = 0');
        $total_records = $dataProduct->count();
        $dataProduct = $dataProduct->limit($limit, $offset);

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
            $temparr['PilihanProduct'] = [];

            // Looping Warna Product 
            $dataPilihanProduct = WarnaProduct::get()->where('ProductID = ' . $product->ID);
            foreach ($dataPilihanProduct as $warnaProduct) {
                $hargaAktif = $this->getHargaAktif($warnaProduct->ID);

                $pil = array();
                $pil['ID'] = $warnaProduct->ID;
                $pil['Warna'] = $warnaProduct->Warna->NamaWarna;
                $pil['Stok'] = $warnaProduct->Stok;
                $pil['Harga'] = $hargaAktif->Harga;
                $pil['TglMulaiBerlaku'] = $hargaAktif->TglMulaiBerlaku;

                $temparr['PilihanProduct'][] = $pil;
            }

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
            "data" => [
                'pages' => $page,
                'limit' => $limit,
                'total_records' => $total_records,
                'records' => $dataArray
            ]
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
                    $warnaProduct->Stok = Convert::raw2sql($_REQUEST['Jumlah'][$i]);
                    $warnaProduct->write();

                    // Harga Product
                    $hargaProduct = HargaProduct::create();
                    $hargaProduct->Harga = str_replace(".", "", Convert::raw2sql($_REQUEST['Harga'][$i]));
                    $hargaProduct->WarnaProductID = $warnaProduct->ID;
                    $hargaProduct->TglMulaiBerlaku = date('Y-m-d H:i:s');
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
                $hargaAktif = $this->getHargaAktif($warnaProduct->ID);

                $temparr = array();
                $temparr['ID'] = $warnaProduct->ID;
                $temparr['Warna'] = $warnaProduct->Warna->NamaWarna;
                $temparr['Stok'] = $warnaProduct->Stok;
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
            $hargaAktif = $this->getHargaAktif($warnaProduct->ID);
            $response = [
                "status" => [
                    "code" => 200,
                    "description" => "OK",
                    "message" => [
                        "Detail Stok & Harga"
                    ]
                ],
                "data" => [
                    "ID" => $warnaProduct->ID,
                    "NamaProduct" => $warnaProduct->Product()->NamaProduct,
                    "WarnaProduct" => $warnaProduct->Warna()->NamaWarna,
                    "JumlahStok" => $warnaProduct->Stok,
                    "Harga" => $hargaAktif->Harga,
                ]
            ];
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function updateHarga(HTTPRequest $request)
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
            $Harga = (isset($_REQUEST['Harga'])) ? $_REQUEST['Harga'] : '';
            $TglHargaMulaiBerlaku = (isset($_REQUEST['TglHargaMulaiBerlaku'])) ? $_REQUEST['TglHargaMulaiBerlaku'] : '';

            if (trim($Harga) == null || trim($TglHargaMulaiBerlaku) == null) {
                $message = [];
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
                if ($hargaTerakhir->Harga == str_replace(".", "", Convert::raw2sql($_REQUEST['Harga']))) {
                    $response = [
                        "status" => [
                            "code" => 304,
                            "description" => "Not Modified",
                            "message" => [
                                "Tidak ada perubahan data"
                            ]
                        ]
                    ];
                } elseif ($hargaTerakhir->Harga != str_replace(".", "", Convert::raw2sql($_REQUEST['Harga']))) {
                    if ($hargaTerakhir->Harga != str_replace(".", "", Convert::raw2sql($_REQUEST['Harga']))) {
                        $hargaProduct = HargaProduct::create();
                        $hargaProduct->Harga = str_replace(".", "", Convert::raw2sql($_REQUEST['Harga']));
                        $hargaProduct->TglMulaiBerlaku = Convert::raw2sql($_REQUEST['TglHargaMulaiBerlaku']);
                        $hargaProduct->WarnaProductID = $id;
                        $hargaProduct->write();
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
                $hargaAktif = $this->getHargaAktif($warnaProduct->ID);

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

    public function updateStok(HTTPRequest $request)
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
                        "Pilihan Product dengan WarnaProductID " . $id . " tidak ditemukan"
                    ]
                ]
            ];
        } else {
            // Validation required
            $Stok = (isset($_REQUEST['Stok'])) ? $_REQUEST['Stok'] : '';

            if (trim($Stok) == null) {
                $response = [
                    "status" => [
                        "code" => 422,
                        "description" => "Unprocessable Entity",
                        "message" => [
                            'Stok tidak boleh kosong'
                        ]
                    ]
                ];
            } else {
                $updateStok = Convert::raw2sql($_REQUEST['Stok']);
                // Validate data modified
                if ($warnaProduct->Stok == $updateStok) {
                    $response = [
                        "status" => [
                            "code" => 304,
                            "description" => "Not Modified",
                            "message" => [
                                "Tidak ada perubahan data"
                            ]
                        ]
                    ];
                } else {
                    // Save History Stok 
                    $jumlahProduct = JumlahProduct::create();
                    $jumlahProduct->Jumlah = ($updateStok - $warnaProduct->Stok);
                    $jumlahProduct->WarnaProductID = $warnaProduct->ID;
                    $jumlahProduct->write();

                    // Update Stok
                    $warnaProduct->update([
                        'Stok' => $updateStok,
                    ]);
                    $warnaProduct->write();

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
                    $temparr['Jumlah'] = $stok->Jumlah;
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
                            "Stok" => $warnaProduct->Stok,
                        ],
                        "historyStok" => $dataStokArray
                    ]
                ];
            }
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }


    public function search(HTTPRequest $request)
    {
        $datas = $request->requestVars();

        $productDetail = Product::get()->where('Deleted = 0');

        if (isset($datas['keyword'])) {
            $query = $datas['keyword'];
            $query = str_replace('-', '', $query);
            $query = Convert::raw2sql($query);

            $sql = "SELECT Product.*, Product.ID AS PID 
            FROM Product 
            INNER JOIN WarnaProduct 
            ON Product.ID = WarnaProduct.ProductID 
            INNER JOIN Warna 
            ON WarnaProduct.WarnaID = Warna.ID 
            WHERE CONCAT_WS(Product.NamaProduct,'-',Warna.NamaWarna) LIKE '%" . strtoupper($query) . "%'
            GROUP BY Product.ID ORDER BY Product.Created DESC ";

            $productDetail = DB::query($sql);

            if ($productDetail->numRecords() > 0) {
                $temp_results = [];
                foreach ($productDetail as $product) {
                    $prod = Product::get()->byID($product['ID']);
                    $warnaProduct = $prod->WarnaProduct();

                    // Looping warna product
                    $temp_warna = [];
                    foreach ($warnaProduct as $warna) {
                        $temp_warna[] = [
                            'NamaWarna' => $warna->Warna()->NamaWarna
                        ];
                    }

                    $temp_results[] = [
                        'ID' => $prod->ID,
                        'NamaProduct' => $prod->NamaProduct,
                        'PilihanProduct' => $temp_warna,
                    ];
                }

                $response = [
                    "status" => [
                        "code" => 200,
                        "description" => "OK",
                        "message" => [
                            'Hasil pencarian untuk ' . $query
                        ]
                    ],
                    "data" => $temp_results

                ];
            } else {
                $response = [
                    "status" => [
                        "code" => 404,
                        "description" => "Not Found",
                        "message" => [
                            'Pencarian dengan keyword ' . $query . ' tidak ditemukan'
                        ]
                    ]
                ];
            }
        } else {
            $response = [
                "status" => [
                    "code" => 404,
                    "description" => "Not Found",
                    "message" => [
                        'Pencarian tidak ditemukan'
                    ]
                ]
            ];
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function suggest(HTTPRequest $request)
    {
        $datas = $request->requestVars();
        $dataProduct = Product::get()->where('Deleted = 0');

        if (isset($datas['keyword'])) {
            $query = $datas['keyword'];
            $query = Convert::raw2sql($query);

            // My Code 

            // $sql = "SELECT NamaProduct, DeskripsiProduct 
            // FROM Product
            // WHERE NamaProduct LIKE '%" . strtoupper($query) . "%'
            // OR DeskripsiProduct LIKE '%" . strtoupper($query) . "%'";


            // Code Mas Fian 

            $sql = "SELECT nama FROM
            (SELECT NamaProduct as nama 
            FROM Product 
            WHERE NamaProduct LIKE '%" . strtoupper($query) . "%'
            UNION ALL 
            SELECT DeskripsiProduct as nama 
            FROM Product
            WHERE DeskripsiProduct LIKE '%" . strtoupper($query) . "%' )
            tablenya GROUP BY nama";

            $dataProduct = DB::query($sql);

            $datasuggest = [];
            foreach ($dataProduct as $product) {
                array_push($datasuggest, $product['nama']);
            }

            $response = [
                "status" => [
                    "code" => 200,
                    "description" => "OK",
                    "message" => [
                        'Suggestion untuk ' . $query
                    ]
                ],
                "data" => $datasuggest
            ];
        } else {
            $response = [
                "status" => [
                    "code" => 404,
                    "description" => "Not Found",
                    "message" => [
                        'Suggestion tidak ditemukan'
                    ]
                ]
            ];
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function filter(HTTPRequest $request)
    {
        $datas = $request->requestVars();

        $dataProduct = Product::get()->where('Deleted = 0');

        $andWhere = "";
        // FILTER BY HARGA PRODUCT
        if (isset($datas['minPrice']) || isset($datas['maxPrice'])) {
            if (isset($datas['minPrice'])) {
                $minPrice = $datas['minPrice'];
                $minPrice = str_replace('.', '', $minPrice);
                $minPrice = Convert::raw2sql($minPrice);

                $andWhere = "AND HargaProduct.Harga >= " . $minPrice;
            }
            if (isset($datas['maxPrice'])) {
                $maxPrice = $datas['maxPrice'];
                $maxPrice = str_replace('.', '', $maxPrice);
                $maxPrice = Convert::raw2sql($maxPrice);
                $andWhere = "AND HargaProduct.Harga <= " . $maxPrice;
            }

            if (isset($datas['maxPrice']) && isset($datas['minPrice'])) {
                $andWhere = "AND HargaProduct.Harga >= " . $minPrice . " AND HargaProduct.Harga <= " . $maxPrice;
            }
        }

        // FILTER BY WARNA PRODUCT
        if (isset($datas['warna'])) {
            $filterWarna = strtoupper($datas['warna']);
            $dataWarna = Warna::get()->where("NamaWarna LIKE '%{$filterWarna}%'");
            $dataIDWarna = [];
            foreach ($dataWarna as $warna) {
                $dataIDWarna[] = $warna->ID;
            }
            $idWarna = '(' . implode(',', $dataIDWarna) . ')';

            $andWhere = $andWhere . " AND WarnaProduct.WarnaID IN " . $idWarna;
        }

        $sql = "SELECT Product.*
        FROM Product 
        INNER JOIN WarnaProduct
        ON WarnaProduct.ProductID = Product.ID
        INNER JOIN HargaProduct 
        ON HargaProduct.WarnaProductID = WarnaProduct.ID
        WHERE HargaProduct.TglMulaiBerlaku <= '" . date("Y-m-d H:i:s") . "'
        " . $andWhere . "
        GROUP BY Product.ID";

        $dataProduct = DB::query($sql);

        if ($dataProduct->numRecords() > 0) {
            $temp_results = [];
            foreach ($dataProduct as $product) {
                $prod = Product::get()->byID($product['ID']);

                $temparr = [];
                $dataPilihanProduct = WarnaProduct::get()->where('ProductID = ' . $product['ID']);
                foreach ($dataPilihanProduct as $warnaProduct) {
                    $hargaAktif = $this->getHargaAktif($warnaProduct->ID);

                    $pil = array();
                    $pil['ID'] = $warnaProduct->ID;
                    $pil['Warna'] = $warnaProduct->Warna->NamaWarna;
                    $pil['Stok'] = $warnaProduct->Stok;
                    $pil['Harga'] = $hargaAktif->Harga;
                    $pil['TglMulaiBerlaku'] = $hargaAktif->TglMulaiBerlaku;

                    $temparr[] = $pil;
                }

                $temp_results[] = [
                    'ID' => $prod->ID,
                    'NamaProduct' => $prod->NamaProduct,
                    'PilihanProduct' => $temparr
                ];
            }

            $response = [
                "status" => [
                    "code" => 200,
                    "description" => "OK",
                    "message" => [
                        'Hasil filter product.'
                    ]
                ],
                "data" => $temp_results
            ];
        } else {
            $response = [
                "status" => [
                    "code" => 404,
                    "description" => "Not Found",
                    "message" => [
                        'Produk tidak ditemukan.'
                    ]
                ]
            ];
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }
}
