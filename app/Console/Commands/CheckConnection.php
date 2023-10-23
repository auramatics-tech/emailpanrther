<?php

namespace Acelle\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Acelle\Model\SendingServer;
use Acelle\Model\TrackingDomain;
use DB;
use Log;

class CheckConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:connection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'checks connection with cohost and sharedhost every 30 mins and add in logs';


    public $cohost_ids;
    public $sharedhost_ids;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->cohost_ids = array();
        $this->sharedhost_ids = array();
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $sending_servers = SendingServer::where('domain_created_attached', 1)->where('status','active')->orderby('id', 'desc')->get();
        if (count($sending_servers)) {
            foreach ($sending_servers as $key => $servers) {
                $sending_identities = json_decode($servers->options);

                if (isset($sending_identities->identities)) {
                    foreach ($sending_identities->identities as $domain_email => $data) {
                        if (isset($data->server_ip) && $data->server_ip) {
                            $domain = $domain_email;
                            $domain = (substr(strrchr($domain, "@"), 1)) ? substr(strrchr($domain, "@"), 1) : $domain_email;
                            $server_ip = $data->server_ip;
                            $settings = [
                                'imap_username' => $servers->imap_username,
                                'imap_password' => $servers->imap_password,
                                'imap_host' => $servers->imap_host,
                                'imap_port' => $servers->imap_port,
                                'smtp_username' => $servers->smtp_username,
                                'smtp_password' => $servers->smtp_password,
                                'host' => $servers->host,
                                'smtp_port' => $servers->smtp_port,
                                'sending_server' => $servers->id
                            ];
                            $this->test($server_ip, $settings,$domain);
                            if (isset($data->proxy_account) && $data->proxy_account)
                                $this->testTrackingDomain($domain, $data->proxy_account, $servers->id, $server_ip);
                        }
                    }
                }
            }
        }
    }

    protected function testTrackingDomain($url, $ee_account, $sending_server, $server_ip)
    {
        $verifyUrl = "https://$url/?q=ok";
        Log::channel('domain_process')->info($verifyUrl);
        try {
            $result = file_get_contents($verifyUrl);
            $this->info('tracking:-' . $result);
            Log::channel('doserver')->info("Tracking domain result:- " . $result);
            if ($result == 'ok') {
                
                $output = shell_exec("cd /var/www/cert_install && python3 dante.py $server_ip");
                $this->info($output.' '. $server_ip);

                return true;
            } else {
                $data['host'] = $url;
                $data['sending_server'] = $sending_server;
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');
                $data['error'] = 'Connection with domain not successfull';
                $data['error_type'] = 2;
                DB::table('connection_logs')->insert($data);
                $this->apache_restart($url, $server_ip);
                // $this->error_mail($ee_account, $url);
                Log::channel('slack')->info('Sending server down:- ' . $url);
                return false;
            }
        } catch (\Throwable $th) {
            Log::channel('slack')->info('CATCH Sending server down:- ' . $url);
            // restart apache
            $this->apache_restart($url, $server_ip);
            // $this->error_mail($ee_account, $url);
            return false;
        }
    }

    protected function update_dante($server_ip){
        $output = shell_exec("cd /var/www/cert_install && python3 dante.py $server_ip");
        $this->info($output.' '. $server_ip);
    }

    protected function apache_restart($domain, $server_ip)
    {
        Log::channel('slack')->info('Restarting apache server');
        
        $output = shell_exec("cd /var/www/cert_install && python3 apache_restart.py $server_ip $domain");
    }

    protected function error_mail($ee_account, $url)
    {
        $mail_data = [
            'from' => [
                'name' => 'Emailpanther Admin',
                'address' => "hello@" . $url
            ],

            'to' => [
                [
                    'name' => 'Sukhwinder Sodhi',
                    'address' => 'sukhwindersodhi62@gmail.com'
                ],
            ],
            'subject' => 'Urgent:- Server domain check failed',
            "html" => 'Server domain check failed for ' . $url
        ];

        $settings['serviceUrl'] = 'https://127.0.0.1';
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('EE_AUTH'),
            'content-type' => 'application/json'
        ])->post(env('EE_BASE') . "settings", $settings);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('EE_AUTH'),
            'content-type' => 'application/json'
        ])->post(env('EE_BASE') . "/account/$ee_account/submit", $mail_data);
    }

    protected function test($server_ip, $settings,$domain)
    {
        $proxy_user = env("PROXY_USER");
        $proxy_password = env("PROXY_PASSWORD");
        $account = [
            'imap' => [
                'auth' => [
                    'user' => $settings['imap_username'],
                    'pass' => $settings['imap_password'],
                ],
                "host" => $settings['imap_host'],
                "port" => $settings['imap_port'],
                "secure" => true,
                "resyncDelay" => 900
            ],
            'smtp' => [
                'auth' => [
                    'user' => $settings['smtp_username'],
                    'pass' => $settings['smtp_password'],
                ],
                "host" => $settings['host'],
                "port" => $settings['smtp_port'],
                "secure" => false
            ],
            'proxy' => "socks5://$proxy_user:$proxy_password@$server_ip:1080"
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('EE_AUTH'),
            'content-type' => 'application/json'
        ])->post(env('EE_BASE') . "/verifyAccount", $account);

        $connection = $response->json();
        $this->info(json_encode($connection));
        if (isset($connection['smtp']['success']) && $connection['smtp']['success']) {
        } elseif (isset($connection['smtp']['error']) && $connection['smtp']['error']) {
            $data['host'] = $settings['host'];
            $data['sending_server'] = $settings['sending_server'];
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            $data['error'] = $connection['smtp']['error'];
            $data['error_type'] = 2;
            Log::channel('slack')->info('Sending server smtp down:- ' . $domain . ' === ' . $connection['smtp']['error']);
            DB::table('connection_logs')->insert($data);
            
            //$this->update_dante($server_ip);
            $this->apache_restart($domain, $server_ip);

        }

        if (isset($connection['imap']['success']) && $connection['imap']['success']) {
        } elseif (isset($connection['imap']['error']) && $connection['imap']['error']) {
            $data['host'] = $settings['host'];
            $data['sending_server'] = $settings['sending_server'];
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            $data['error'] = $connection['imap']['error'];
            $data['error_type'] = 2;
            Log::channel('slack')->info('Sending server imap down:- ' . $domain . ' === ' . $connection['imap']['error']);
            DB::table('connection_logs')->insert($data);
        }
    }
}
