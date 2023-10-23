<?php

namespace Acelle\Console\Commands;

use Acelle\Model\Campaign;
use Acelle\Model\CampaignSendingServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Acelle\Model\Subscriber;
use Acelle\Model\EmailCount;
use Acelle\Model\TrackingLog;
use Acelle\Model\OpenLog;
use Acelle\Model\ClickLog;
use DB;
use Log;
use Carbon\Carbon;

class FeedDashboard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feed:dashboard';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'checks every hour and inform on slack if any campaign mails in hr is less according to the sending server speed';

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
        date_default_timezone_set('UTC');
        $campaigns = Campaign::where("status", '!=', 'paused')
            ->orderby('id', 'desc')->get();

        // $endDate = Carbon::today();
        // $startDate = Carbon::today()->subMonth();
        // while ($startDate->lte($endDate)) {
        //     $dates[] = $startDate->toDateString();
        //     $startDate->addDay();
        // }

        $dates[] = date("Y-m-d");

        if (count($campaigns)) {
            foreach ($campaigns as $campaign) {
                foreach ($dates as $date) {
                    $this->info('Updating '.$campaign->id.' date '.$date);
                    $data['date'] = $date;
                    $data['count'] = TrackingLog::where('campaign_id', $campaign->id)->whereDate('created_at', $date)->count();
                    $data['count_open'] = OpenLog::join('tracking_logs', 'tracking_logs.message_id', '=', 'open_logs.message_id')
                        ->where('tracking_logs.campaign_id', $campaign->id)
                        ->whereDate('open_logs.created_at', $date)->count();
                    $data['count_click'] = ClickLog::join('tracking_logs', 'tracking_logs.message_id', '=', 'click_logs.message_id')
                        ->where('tracking_logs.campaign_id', $campaign->id)
                        ->whereDate('click_logs.created_at', $date)->count();
                    $data['campaign_id'] = $campaign->id;
                    
                    EmailCount::updateorcreate(['date'=>$date,'campaign_id'=>$campaign->id],$data);
                }
            }
        }
    }
}
