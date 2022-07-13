<?php

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\PaginatedList;

class WarnaController extends PageController
{
    private static $allowed_actions = [
        'store',
        'show',
        'update',
        'delete'
    ];

    public function index(HTTPRequest $request)
    {
        $dataArray = array();
        if (isset($_REQUEST['Status'])) {
            $warna = Warna::get()->where('Deleted = 0 AND Status = ' . $_REQUEST['Status']);
        } else {
            $warna = Warna::get()->where('Deleted = 0')->limit(10);
        }
        $dataWarna =  new PaginatedList($warna, $this->getRequest());

        foreach ($dataWarna as $warna) {
            $temparr = array();
            $temparr['ID'] = $warna->ID;
            $temparr['NamaWarna'] = $warna->NamaWarna;
            $temparr['Status'] = $warna->Status;
            $dataArray[] = $temparr;
        }

        $response = [
            "status" => [
                "code" => 200,
                "description" => "OK",
                "message" => [
                    "List Warna"
                ],
            ],
            "data" => $dataArray
        ];

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function store(HTTPRequest $request)
    {
        // Validation required 
        $NamaWarna = (isset($_REQUEST['NamaWarna'])) ? $_REQUEST['NamaWarna'] : '';
        if (trim($NamaWarna) == null) {
            $response = [
                "status" => [
                    "code" => 422,
                    "description" => "Unprocessable Entity",
                    "message" => [
                        "Nama warna tidak boleh kosong"
                    ]
                ]
            ];
        } else {
            // Validation unique
            $check_warna = Warna::get()->where('Deleted = 0')->filter([
                'NamaWarna' => Convert::raw2sql($NamaWarna)
            ]);
            if (count($check_warna) != 0) {
                $response = [
                    "status" => [
                        "code" => 409,
                        "description" => "Conflict",
                        "message" => [
                            "Nama warna sudah tersedia"
                        ]
                    ],
                ];
            } else {
                $warna = Warna::create();
                $warna->NamaWarna = Convert::raw2sql($NamaWarna);
                $warna->Status = 1;
                $warna->write();

                $response = [
                    "status" => [
                        "code" => 201,
                        "description" => "Created",
                        "message" => [
                            "Berhasil disimpan"
                        ]
                    ],
                ];
            }
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function show(HTTPRequest $request)
    {
        $id = $request->params()["ID"];
        $warna = Warna::get()->byID($id);

        if (is_null($warna)) {
            $response = [
                "status" => [
                    "code" => 404,
                    "description" => "Not Found",
                    "message" => [
                        "Warna dengan ID " . $id . " tidak ditemukan"
                    ]
                ]
            ];
        } else {
            $response = [
                "status" => [
                    "code" => 200,
                    "description" => "OK",
                    "message" => [
                        "Detail Warna"
                    ],
                ],
                "data" => [
                    "ID" => $warna->ID,
                    "NamaWarna" => $warna->NamaWarna,
                    "Status" => $warna->Status
                ]
            ];
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function update(HTTPRequest $request)
    {
        $id = $request->params()["ID"];
        $warna = Warna::get()->byID($id);

        // Validation null 
        if (is_null($warna)) {
            $response = [
                "status" => [
                    "code" => 404,
                    "description" => "Not Found",
                    "message" => [
                        "Warna dengan ID " . $id . " tidak ditemukan"
                    ]
                ]
            ];
        } else {
            // Validation required
            $NamaWarna =  (isset($_REQUEST['NamaWarna'])) ? $_REQUEST['NamaWarna'] : '';
            $Status =  (isset($_REQUEST['Status'])) ? $_REQUEST['Status'] : '';

            if (trim($NamaWarna) == null || trim($Status) == null) {
                $message = [];
                if (trim($NamaWarna) == null) {
                    array_push($message, "Nama warna tidak boleh kosong");
                }
                if (trim($Status) == null) {
                    array_push($message, "Silahkan pilih status");
                }
                $response = [
                    "status" => [
                        "code" => 422,
                        "description" => "Unprocessable Entity",
                        "message" => $message
                    ]
                ];
            } else {
                // Validation unique
                $check_warna = Warna::get()->where('Deleted = 0')->filter([
                    'NamaWarna' => Convert::raw2sql($NamaWarna)
                ])->first();
                if (!is_null($check_warna) && $check_warna->ID != $warna->ID) {
                    $response = [
                        "status" => [
                            "code" => 409,
                            "description" => "Conflict",
                            "message" => [
                                "Nama warna sudah tersedia"
                            ]
                        ],
                    ];
                } else {
                    $warna->update([
                        'NamaWarna' => Convert::raw2sql($NamaWarna),
                        'Status' => Convert::raw2sql($Status)
                    ]);
                    $warna->write();
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

    public function delete(HTTPRequest $request)
    {
        $id = $request->params()["ID"];
        $warna = Warna::get()->byID($id);

        // Validation null 
        if (is_null($warna)) {
            $response = [
                "status" => [
                    "code" => 404,
                    "description" => "Not Found",
                    "message" => [
                        "Warna dengan ID " . $id . " tidak ditemukan"
                    ]
                ]
            ];
        } else {
            $warna->update([
                'Deleted' => 1
            ]);
            $warna->write();

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

        // Kurang Validasi Ketika ID Sudah Digunakan Pada Table Lain 
    }
}
