<?php

namespace Acelle\Console\Commands;

use Acelle\Model\Campaign;
use Acelle\Model\CampaignSendingServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Acelle\Model\SendingServer;
use Acelle\Model\TrackingDomain;
use Acelle\Model\TrackingLog;
use DB;
use Log;

class ErrorResume extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'error:resume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resume campaigns if pause';

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
        $date = date('Y-m-d H:', strtotime('-1 hour'));
        $now = date("Y-m-d", strtotime('-1 hour'));
        $time = date('H', strtotime('-1 hour'));
        Log::channel('slack')->info('checking campaign speed ' . $date);
        $campaigns = Campaign::where('status', '!=', 'paused')
            ->where('status', '!=', 'new')
            ->get();
        $c_count = 0;
        if (count($campaigns) && $time <= 23) {
            foreach ($campaigns as $campaign) {
                // first mail
                $first_mail = TrackingLog::where('campaign_id', $campaign->id)->orderby('id', 'asc')->first();
                if (strtotime(date('Y-m-d', strtotime($first_mail->created_at))) != strtotime($now)) {

                    $campaign_sending_server = CampaignSendingServer::where('campaign_id', $campaign->id)->first();
                    if (isset($campaign_sending_server->id)) {
                        $sending_server = SendingServer::where('id', $campaign_sending_server->sending_server_id)->first();
                        if (isset($sending_server->id)) {
                            if ($sending_server->quota_unit == 'minute') {
                                $speed = ($sending_server->quota_value * 60) / $sending_server->quota_base;
                                $check_mail_sends = TrackingLog::where('campaign_id', $campaign->id)->where('created_at', 'like', "$date%")->count() - $campaign->count_extra_last_hr;

                                $val = 60 * $time;

                                $speed_day = ($sending_server->quota_value * $val) / $sending_server->quota_base;
                                $mails_day = TrackingLog::where('campaign_id', $campaign->id)->where('created_at', 'like', "$now%")->count();
                                if ($speed_day > $mails_day) {
                                    $this->info($campaign->uid . '======' . $mails_day . "==========" . $speed_day . "===========" . $speed_day - $mails_day);
                                    $next_hr_extra_mails = $speed_day - $mails_day;
                                    $campaign->last_hr_extra_mails = $next_hr_extra_mails;
                                    $campaign->count_extra_last_hr = $next_hr_extra_mails;
                                    $campaign->save();
                                    Log::channel('slack')->info('Sending Speed not perfect for ' . $date . "=====$now". '======' . $campaign->uid . '======' . $mails_day . "==========" . $speed_day . "===========" . $speed_day - $mails_day);
                                } else {
                                    // Log::channel('slack')->info('Sending Speed perfect for ' . $date . '======' . $campaign->id . '======' . $mails_day . "==========" . $speed_day . "===========" . $speed_day - $mails_day);
                                
                                    $campaign->last_hr_extra_mails = 0;
                                    $campaign->count_extra_last_hr = 0;
                                    $campaign->save();
                                }
                                // if ($check_mail_sends < $speed) {
                                //     $next_hr_extra_mails = $speed - $check_mail_sends;
                                //     $campaign->last_hr_extra_mails = $next_hr_extra_mails;
                                //     $campaign->count_extra_last_hr = $next_hr_extra_mails;
                                //     $campaign->save();
                                //     Log::channel('slack')->info('Sending Speed not perfect for ' . $campaign->uid . ' mails sent ' . $check_mail_sends . '---' . $campaign->count_extra_last_hr . ' Time ' . $date . '-- day count' . $mails_day);
                                // } else {
                                //     $campaign->last_hr_extra_mails = 0;
                                //     $campaign->count_extra_last_hr = 0;
                                //     $campaign->save();
                                // }
                            }
                        }
                    }
                }
            }
        }
    }
}
