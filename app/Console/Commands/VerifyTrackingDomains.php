<?php

namespace Acelle\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Acelle\Model\TrackingDomain;
use Acelle\Model\User;
use Log;

class VerifyTrackingDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verify:tracking';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify tracking domains';

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
        $TrackingDomain = TrackingDomain::where("status", 'unverified')->get();
        if (count($TrackingDomain)) {
            foreach ($TrackingDomain as $tracking) {
                $user = User::where('customer_id', $tracking->customer_id)->first();
                if ($tracking->name) {
                    Log::channel('doserver')->info("Tracking domain:- " . $tracking->name);
                    $url = $tracking->name;
                    $verifyUrl = "https://$url/ok";
                    try {
                        $result = file_get_contents($verifyUrl);
                        Log::channel('doserver')->info("Tracking domain result:- " . $result);
                        if ($result == 'ok') {
                            Log::channel('doserver')->info("Tracking domains ok:- " . $tracking->name);
                            $tracking->setVerified();
                            $tracking->save();

                            // $user->domain_created_attached = 1;
                            // $user->save();
                        }
                        //code...
                    } catch (\Throwable $th) {
                        Log::channel('doserver')->info("Tracking domains failed:- " . $tracking->name);
                        $output = shell_exec("cd /var/www/cert_install && python3 ssl.py $user->server_ip $tracking->name");
                        Log::channel('domain_process')->info('SSL:-' . $output);
                    }
                }
            }
        }
    }
}
