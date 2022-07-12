<?php

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Environment;


class AuthController extends PageController
{
    private static $allowed_actions = [
        'login',
        'register',
        'changepassword'
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
                $secret  = Environment::getEnv('SS_API_SECRET_KEY');
                // $dateTime = date("Y-m-d H:i:s");
                // $convertedTime = date('Y-m-d H:i:s', strtotime('+60 minutes', strtotime($dateTime)));
                // $data = $user->Email;
                // $token = hash_hmac('sha256', $data, $secret);

                // BUAT TOKEN DISINI
                $payload = [
                    'session_id' => md5(rand()),
                    'email' => $user->Email
                ];
                $token = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

                // $jwt_decode = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $jwt)[1]))));

                $response = [
                    "status" => [
                        "code" => 200,
                        "description" => "OK",
                        "message" => [
                            'Login berhasil'
                        ]
                    ],
                    "data" => [
                        "token" => $token,
                        // "jwt_decode" => $jwt_decode,
                        // 'NamaLengkap' => $user->NamaLengkap,
                        // 'Email' => $user->Email,
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
        // 
    }
}
