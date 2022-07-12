<?php

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Environment;


class AuthController extends PageController
{
    private static $allowed_actions = [
        'login',
        'register',
        'changepassword',
        'logout',
    ];

    public function init()
    {
        parent::init();
    }


    public function login(HTTPRequest $request)
    {
        $Email = (isset($_REQUEST['Email'])) ? $_REQUEST['Email'] : '';
        $Password = (isset($_REQUEST['Password'])) ? $_REQUEST['Password'] : '';
        // Validation required 
        if (trim($Email) == null || trim($Password) == null) {
            $message = [];
            if (trim($Email) == null) {
                array_push($message, "Email tidak boleh kosong");
            }
            if (trim($Password) == null) {
                array_push($message, "Password tidak boleh kosong");
            }
            $response = [
                "status" => [
                    "code" => 422,
                    "description" => "Unprocessable Entity",
                    "message" => $message
                ]
            ];
        } else {
            // Validate email user 
            $user = User::get()->filter(['Email' => $Email])->first();
            if (is_null($user)) {
                $response = [
                    "status" => [
                        "code" => 422,
                        "description" => "Unprocessable Entity",
                        "message" => [
                            'Email tidak terdaftar'
                        ]
                    ]
                ];
            }

            // Check Password
            if (password_verify($Password, $user->Password) == 1) {
                $token = md5($Email . date("Y-m-d H:i:s"));

                $user->update([
                    'Token' => $token,
                ]);
                $user->write();

                $response = [
                    "status" => [
                        "code" => 200,
                        "description" => "OK",
                        "message" => [
                            'Login berhasil'
                        ]
                    ],
                    "data" => [
                        "Token" => $token
                    ]
                ];
            } else {
                $response = [
                    "status" => [
                        "code" => 422,
                        "description" => "Unprocessable Entity",
                        "message" => [
                            'Password salah'
                        ]
                    ]
                ];
            }
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function register(HTTPRequest $request)
    {
        $NamaLengkap = (isset($_REQUEST['NamaLengkap'])) ? $_REQUEST['NamaLengkap'] : '';
        $Email = (isset($_REQUEST['Email'])) ? $_REQUEST['Email'] : '';
        $Password = (isset($_REQUEST['Password'])) ? $_REQUEST['Password'] : '';
        $KonfirmasiPassword = (isset($_REQUEST['KonfirmasiPassword'])) ? $_REQUEST['KonfirmasiPassword'] : '';
        // Validation required 
        if (trim($NamaLengkap) == null || trim($Email) == null || trim($Password) == null) {
            $message = [];
            if (trim($NamaLengkap) == null) {
                array_push($message, "Nama lengkap tidak boleh kosong");
            }
            if (trim($Email) == null) {
                array_push($message, "Email tidak boleh kosong");
            }
            if (trim($Password) == null) {
                array_push($message, "Password tidak boleh kosong");
            }

            $response = [
                "status" => [
                    "code" => 422,
                    "description" => "Unprocessable Entity",
                    "message" => $message
                ]
            ];
        } else {
            // Validation unique email
            $check_user = User::get()->filter(['Email' => $Email])->first();
            if (!is_null($check_user)) {
                $response = [
                    "status" => [
                        "code" => 409,
                        "description" => "Conflict",
                        "message" => [
                            'Email sudah digunakan oleh user lain'
                        ]
                    ]
                ];
            } elseif (strlen($Password) < 8 || $Password != $KonfirmasiPassword) {
                // Validate Match Password & Confirm Password 
                $message = [];
                if (strlen($Password) < 8) {
                    array_push($message, "Password minimal terdiri 8 karakter.");
                }
                if ($Password != $KonfirmasiPassword) {
                    array_push($message, "Password dan konfirmasi password tidak sesuai.");
                }
                $response = [
                    "status" => [
                        "code" => 422,
                        "description" => "Unprocessable Entity",
                        "message" => $message
                    ]
                ];
            } elseif ($Password == $KonfirmasiPassword) {

                // Post data User 
                $user = User::create();
                $user->NamaLengkap = Convert::raw2sql($_REQUEST['NamaLengkap']);
                $user->Email = Convert::raw2sql($_REQUEST['Email']);
                $user->Password = password_hash($_REQUEST['Password'], PASSWORD_DEFAULT);
                $user->write();

                $response = [
                    "status" => [
                        "code" => 201,
                        "description" => "Created",
                        "message" => [
                            'Registrasi berhasil'
                        ]
                    ],
                    "data" => [
                        "NamaLengkap" => $user->NamaLengkap,
                        "Email" => $user->Email
                    ]
                ];
            }
        }

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function changepassword(HTTPRequest $request)
    {
        $PasswordLama = (isset($_REQUEST['PasswordLama'])) ? $_REQUEST['PasswordLama'] : '';
        $PasswordBaru = (isset($_REQUEST['PasswordBaru'])) ? $_REQUEST['PasswordBaru'] : '';
        $KonfirmasiPassword = (isset($_REQUEST['KonfirmasiPassword'])) ? $_REQUEST['KonfirmasiPassword'] : '';
        // Validate Required 
        if (trim($PasswordLama) == null || trim($PasswordBaru) == null) {
            $message = [];
            if (trim($PasswordLama) == null) {
                array_push($message, "Password lama tidak boleh kosong");
            }
            if (trim($PasswordBaru) == null) {
                array_push($message, "Password baru tidak boleh kosong");
            }

            $response = [
                "status" => [
                    "code" => 422,
                    "description" => "Unprocessable Entity",
                    "message" => $message
                ]
            ];
        } else {
            // Check user
            $getHeaders = apache_request_headers();
            $bearer = $getHeaders['Authorization'];
            $token = substr($bearer, 7);

            $userLogin = User::get()->filter(['Token' => $token])->first();
            $check = password_verify($PasswordLama, $userLogin->Password);
            
            // Validate Old Password
            if (password_verify($PasswordLama, $userLogin->Password) == 1) {

                // Validate New Password 
                if (strlen($_REQUEST['PasswordBaru']) < 8) {
                    $response = [
                        "status" => [
                            "code" => 422,
                            "description" => "Unprocessable Entity",
                            "message" => [
                                'Password baru minimal terdiri 8 karakter'
                            ]
                        ]
                    ];
                } elseif ($_REQUEST['PasswordBaru'] == $_REQUEST['KonfirmasiPassword']) {
                    $userLogin->update([
                        'Password' => password_hash($_REQUEST['PasswordBaru'], PASSWORD_DEFAULT)
                    ]);
                    $userLogin->write();
                    $response = [
                        "status" => [
                            "code" => 200,
                            "description" => "OK",
                            "message" => [
                                'Password berhasil diganti'
                            ]
                        ]
                    ];
                } else {
                    $response = [
                        "status" => [
                            "code" => 422,
                            "description" => "Unprocessable Entity",
                            "message" => [
                                'Password baru dan Konfirmasi password harus sama'
                            ]
                        ]
                    ];
                }
            } else {
                $response = [
                    "status" => [
                        "code" => 422,
                        "description" => "Unprocessable Entity",
                        "message" => [
                            'Password lama tidak sesuai'
                        ]
                    ]
                ];
            }
        }
        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }

    public function logout(HTTPRequest $request)
    {
        $getHeaders = apache_request_headers();
        $bearer = $getHeaders['Authorization'];
        $token = substr($bearer, 7);

        $userLogin = User::get()->filter(['Token' => $token])->first();
        $userLogin->update([
            'Token' => null,
        ]);
        $userLogin->write();

        $response = [
            "status" => [
                "code" => 200,
                "description" => "OK",
                "message" => [
                    'Logout berhasil'
                ]
            ],
        ];

        $this->response->addHeader('Content-Type', 'application/json');
        return json_encode($response);
    }
}
