<?php

namespace {

    use GuzzleHttp\Psr7\Request;
    use SilverStripe\CMS\Controllers\ContentController;
    use SilverStripe\Control\Director;
    use SilverStripe\Control\HTTPRequest;
    use SilverStripe\Core\Environment;

    class PageController extends ContentController
    {
        /**
         * An array of actions that can be accessed via a request. Each array element should be an action name, and the
         * permissions or conditions required to allow the user to access it.
         *
         * <code>
         * [
         *     'action', // anyone can access this action
         *     'action' => true, // same as above
         *     'action' => 'ADMIN', // you must have ADMIN permissions to access this action
         *     'action' => '->checkAction' // you can only access this action if $this->checkAction() returns true
         * ];
         * </code>
         *
         * @var array
         */
        private static $allowed_actions = [
            'getUserLogin'
        ];


        protected function init()
        {
            parent::init();
            // You can include any CSS or JS required by your project here.
            // See: https://docs.silverstripe.org/en/developer_guides/templates/requirements/


            // Check SS_API_SECRET_KEY

            $secret  = Environment::getEnv('SS_API_SECRET_KEY');
            $getHeaders = apache_request_headers();
            $SS_API_SECRET_KEY = isset($getHeaders['SS_API_SECRET_KEY']) ? $getHeaders['SS_API_SECRET_KEY'] : "";
            if (empty($SS_API_SECRET_KEY) || $secret != $SS_API_SECRET_KEY) {
                $response = [
                    "status" => [
                        "code" => 400,
                        "description" => "Bad Request",
                        "message" => [
                            'Invalid SS_API_SECRET_KEY'
                        ]
                    ]
                ];
                $this->response->addHeader('Content-Type', 'application/json');
                echo json_encode($response);
                die;
            }

            // Check Token 
            $url = $_SERVER['REQUEST_URI'];
            if ($url != "/silverstripe_api/api/v1/auth/login" && $url != "/silverstripe_api/api/v1/auth/register") {
                $getHeaders = apache_request_headers();
                $token = isset($getHeaders['Authorization']) ? $getHeaders['Authorization'] : "";
                if (empty($token)) {
                    $response = [
                        "status" => [
                            "code" => 401,
                            "description" => "Unauthorized",
                            "message" => [
                                'Invalid Token'
                            ]
                        ]
                    ];
                    $this->response->addHeader('Content-Type', 'application/json');
                    echo json_encode($response);
                    die;
                } else {
                    $bearer = $getHeaders['Authorization'];
                    $token = substr($bearer, 7);

                    $userLogin = User::get()->filter(['Token' => $token])->first();

                    if (is_null($userLogin)) {
                        $response = [
                            "status" => [
                                "code" => 401,
                                "description" => "Unauthorized",
                                "message" => [
                                    'Invalid Token'
                                ]
                            ]
                        ];
                        $this->response->addHeader('Content-Type', 'application/json');
                        echo json_encode($response);
                        die;
                    }
                }
            }
        }

        public function getUserLogin()
        {
            $getHeaders = apache_request_headers();
            $bearer = $getHeaders['Authorization'];
            $token = substr($bearer, 7);

            $userLogin = User::get()->filter(['Token' => $token])->first();
            return $userLogin;
        }
    }
}
