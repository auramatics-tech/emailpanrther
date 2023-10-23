<?php

namespace Acelle\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Acelle\Model\User;
use Acelle\Model\TrackingDomain;
use Log;

class AttachServerDomain extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attach:domain';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Attach server with domains';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $users = User::whereNotNull("droplet_id")->whereNull('server_ip')->where('domain_created_attached', 0)->get();
        if (count($users)) {
            Log::channel('doserver')->info("Cron:-- IP UPDATE" . json_encode($users));
            foreach ($users as $user) {
                $droplet_response =  Http::withHeaders([
                    'Authorization' => 'Bearer ' . env('DO_KEY'),
                    'content-type' => 'application/json'
                ])->get(env('DO_DROPLET') . "/droplets/$user->droplet_id");

                $droplet_data = $droplet_response->json();
                Log::channel('doserver')->info("Server details:-" . json_encode($droplet_data));
                if (isset($droplet_data['droplet']['networks']['v4'][0]['ip_address']) && count($droplet_data['droplet']['networks']['v4'])) {
                    foreach ($droplet_data['droplet']['networks']['v4'] as $networks) {
                        if ($networks['type'] == 'public') {
                            if (!$user->server_ip) {
                                $install_ssl = 1;
                            }
                            $user->server_ip = $networks['ip_address'];
                            $user->save();
                        }
                    }


                    if ($user->server_ip) {
                        $a_record = [
                            'items' => [
                                [
                                    'rrset_type' => 'A',
                                    'rrset_values' => [
                                        $user->server_ip
                                    ]
                                ]
                            ]
                        ];
                        Log::channel('doserver')->info($a_record);
                        $user_domains = TrackingDomain::where(['customer_id' => $user->customer_id])->get();
                        if (count($user_domains)) {
                            foreach ($user_domains as $domain) {
                                $server =  Http::withHeaders([
                                    'Authorization' => 'Apikey ' . env('GANDI_API_KEY'),
                                    'content-type' => 'application/json'
                                ])->put(env('GANDI') . "/livedns/domains/$domain->name/records/@", $a_record);

                                if (isset($server['message']) && $server['message'] == 'DNS Record Created') {
                                    $user->domain_created_attached = 1;
                                    $user->save();
                                }
                                Log::channel('doserver')->info("Cron:-- Droplet id:- $user->droplet_id Domain:- $domain" . json_encode($server->json()));
                                if (isset($install_ssl) && $install_ssl) {
                                    $output = shell_exec("cd /var/www/cert_install && python3 ssl.py $user->server_ip $domain");
                                    Log::channel('domain_process')->info('SSL:-' . $output);
                                }
                            }
                        }
                        $user->save();
                    }
                }
            }
        }
    }
}
