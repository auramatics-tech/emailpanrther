<?php

namespace Acelle\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Acelle\Model\BounceHandler;
use Acelle\Model\SendingServer;
use Log;

class BounceProcessor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bounce:processor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'IMAP/POP3 feature using EmailEngine to check all IMAP accounts and then copy all incoming emails from all IMAP/POP3 accounts (bounce processors) to our master catchall IMAP/POP3 -> admin@emailpanther.co {leave originals, do not delete them}';

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
        $sending_servers = SendingServer::where('status', 'active')->get();
        if (count($sending_servers)) {
            foreach ($sending_servers as $server) {
                if (isset(json_decode($server->options)->identities)) {
                    $bounce_handlers = json_decode($server->options)->identities;
                    foreach ($bounce_handlers as $sending_email => $data) {
                        $account =  $data->proxy_account;
                        $response = Http::withHeaders([
                            'Authorization' => 'Bearer ' . env('EE_AUTH'),
                            'content-type' => 'application/json'
                        ])->get(env('EE_BASE') . "/account/$account/message/AAAAAQAAACg?textType=*");
                        echo "<pre>";
                        print_r($response->json());
                        die;
                    }
                }
            }
        }
    }
}
// AAAAAQAAACg