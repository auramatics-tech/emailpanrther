<?php

namespace Acelle\Http\Controllers;

use Illuminate\Http\Request;
use Acelle\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class TestingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function smtpTest(Request $request)
    {
        $proxy_user = env("PROXY_USER");
        $proxy_password = env("PROXY_PASSWORD");
        $account = [
            'imap' => [
                'auth' => [
                    'user' => 'hello@authoritytech.agency',
                    'pass' => 'export3891',
                ],
                "host" => 'monday.mxrouting.net',
                "port" => 993,
                "secure" => true,
                "resyncDelay" => 900
            ],
            'smtp' => [
                'auth' => [
                    'user' => 'cohost/smtp1',
                    'pass' => 'cW2yODsG3rJIKPRFuaquAUcJ',
                ],
                "host" => 'cohost.email',
                "port" => 587,
                "secure" => false
            ],
            'proxy' => "socks5://$proxy_user:$proxy_password@157.245.87.203:1080"
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('EE_AUTH'),
            'content-type' => 'application/json'
        ])->post(env('EE_BASE') . "/verifyAccount", $account);

        $connection = $response->json();

        echo "<pre>";
        print_r($connection); die;
    }

}
