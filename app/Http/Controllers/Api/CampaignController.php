<?php

namespace Acelle\Http\Controllers\Api;

use Acelle\Http\Controllers\Controller;
use Acelle\Model\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PDO;

/**
 * /api/v1/campaigns - API controller for managing campaigns.
 */
class CampaignController extends Controller
{

    public $subscriber;

    public function __construct()
    {
        $this->subscriber = '';
    }

    /**
     * Display all user's campaigns.
     *
     * GET /api/v1/campaigns
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = \Auth::guard('api')->user();

        date_default_timezone_set('UTC');

        $lists = \Acelle\Model\Campaign::getAll()
            ->select(
                'uid',
                'name',
                'type',
                'subject',
                // 'plain',
                'from_email',
                'from_name',
                'reply_to',
                'campaigns.status',
                'delivery_at',
                'campaigns.created_at',
                'campaigns.updated_at'
            )
            ->where('campaigns.customer_id', '=', $user->customer->id)
            ->leftJoin("tracking_logs", 'tracking_logs.campaign_id', '=', 'campaigns.id')
            ->leftJoin("open_logs", 'open_logs.message_id', '=', 'tracking_logs.message_id')
            ->leftJoin("click_logs", 'click_logs.message_id', '=', 'tracking_logs.message_id')
            ->where(function ($query) use ($request) {
                $query->whereDate('tracking_logs.created_at', date('Y-m-d', strtotime($request->date)))
                    ->orwhereDate('open_logs.updated_at', date('Y-m-d', strtotime($request->date)))
                    ->orwhereDate('click_logs.updated_at', date('Y-m-d', strtotime($request->date)));
            })
            ->where('campaigns.status', '!=', 'paused')
            ->where('from_email', $request->from_email)
            ->groupby('campaigns.id')
            ->orderby('campaigns.id', 'desc')
            ->get();

        return \Response::json($lists, 200);
    }

    public function store(Request $request)
    {
        $user = \Auth::guard('api')->user();

        $lists = \Acelle\Model\Campaign::getAll()
            ->select('uid', 'name', 'type', 'subject', 'plain', 'from_email', 'from_name', 'reply_to', 'campaigns.status', 'delivery_at', 'campaigns.created_at', 'campaigns.updated_at')
            ->where('campaigns.customer_id', '=', $user->customer->id)
            ->leftJoin("tracking_logs", 'tracking_logs.campaign_id', '=', 'campaigns.id')
            ->leftJoin("open_logs", 'open_logs.message_id', '=', 'tracking_logs.message_id')
            ->leftJoin("click_logs", 'click_logs.message_id', '=', 'tracking_logs.message_id')
            ->where(function ($query) use ($request) {
                $query->whereDate('tracking_logs.updated_at', $request->date)
                    ->orwhereDate('open_logs.updated_at', $request->date)
                    ->orwhereDate('click_logs.updated_at', $request->date);
            })
            ->where('campaigns.status', '!=', 'paused')
            ->where('from_email', $request->from_email)
            ->groupby('campaigns.id')
            ->orderby('campaigns.id', 'desc')
            ->get();

        return \Response::json($lists, 200);
    }

    /**
     * Display the specified campaign information.
     *
     * GET /api/v1/campaigns/{id}
     *
     * @param int $id Campaign's id
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $user = \Auth::guard('api')->user();

        $item = \Acelle\Model\Campaign::findByUid($id);
        // check if item exists
        if (!$item) {
            return \Response::json(array('message' => 'Campaign not found'), 404);
        }

        // authorize
        if (!$user->can('read', $item)) {
            return \Response::json(array('message' => 'Unauthorized'), 401);
        }

        date_default_timezone_set('UTC');
        // statistics
        $campaign = [
            'uid' => $item->uid,
            'name' => $item->name,
            'list' => ($item->defaultMailList ? $item->defaultMailList->uid : ''),
            'segment' => ($item->segment ? $item->segment->name : ''),
            'from_email' => $item->from_email,
            'from_name' => $item->from_name,
            'remind_message' => $item->remind_message,
            'status' => $item->status,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];

        $tracking_logs = $item->trackingLogsApi($request->date)->get();

        // statistics
        $statistics = [
            'subscriber_count' => $item->subscribersCount(),
            'uniq_open_rate' => $item->openRate(),
            'delivered_rate' => $item->deliveredRate(),
            'unique_open_count' => $item->uniqueOpenCount(),
            'open_rate' => $item->openRate(),
            'uniq_open_count' => $item->openUniqCount(),
            'last_open' => ($item->lastOpen() ? $item->lastOpen()->created_at : ''),
            'click_rate' => $item->clickRate(),
            'click_count' => $item->clickCount(),
            'abuse_feedback_count' => $item->abuseFeedbackCount(),
            'last_click' => ($item->lastClick() ? $item->lastClick()->created_at : ''),
            'click_count' => $item->clickCount(),
            'bounce_count' => $item->bounceCount(),
            'unsubscribe_count' => $item->unsubscribeCount(),
            'links' => $item->getTopLinks()->get()->pluck(['url']),
            'top_locations' => $item->topLocations()->get()->pluck('ip_address'),
        ];

        return \Response::json(['campaign' => $campaign, 'statistics' => $statistics, 'tracking_logs' => $tracking_logs], 200);
    }


    public function get_tag_value(Request $request)
    {
        $subscriber = Subscriber::find($request->subscriber_id);

        return response()->json(['html' => $this->replace_tag($request->html, $subscriber)]);
    }

    protected function replace_tag($html, $subscriber)
    {
        $tags['LIST_UID'] = $subscriber->mailList->uid;
        $tags['LIST_NAME'] = $subscriber->mailList->name;
        $tags['LIST_FROM_NAME'] = $subscriber->mailList->from_name;
        $tags['LIST_FROM_EMAIL'] = $subscriber->mailList->from_email;
        $updateProfileUrl = $subscriber->generateUpdateProfileUrl();
        # Subscriber custom fields
        foreach ($subscriber->mailList->fields as $field) {
            $tags['SUBSCRIBER_' . $field->tag] = $subscriber->getValueByField($field);
            $tags[$field->tag] = $subscriber->getValueByField($field);
        }
        // Special / shortcut fields
        $tags['NAME'] = $subscriber->getFullName();
        $tags['FULL_NAME'] = $subscriber->getFullName();

        foreach ($tags as $tag => $value) {
            $html = str_replace('{' . $tag . '}', $value, $html);
        }

        return $html;
    }

    /**
     * Pause campaign.
     *
     * GET /api/v1/campaigns/{id}
     *
     * @param int $id Campaign's id
     *
     * @return \Illuminate\Http\Response
     */
    public function pause($id)
    {
        $user = \Auth::guard('api')->user();

        $campaign = \Acelle\Model\Campaign::where('uid', '=', $id)
            ->first();

        // check if item exists
        if (!$campaign) {
            return \Response::json(array('message' => 'Campaign not found'), 404);
        }

        // authorize
        if (!$user->can('pause', $campaign)) {
            return \Response::json(array('message' => 'Unauthorized'), 401);
        }

        $campaign->pause();

        // statistics
        $campaign = [
            'uid' => $campaign->uid,
            'name' => $campaign->name,
            'list' => ($campaign->mailList ? $campaign->mailList->name : ''),
            'segment' => ($campaign->segment ? $campaign->segment->name : ''),
            'from_email' => $campaign->from_email,
            'from_name' => $campaign->from_name,
            'remind_message' => $campaign->remind_message,
            'status' => $campaign->status,
            'created_at' => $campaign->created_at,
            'updated_at' => $campaign->updated_at,
        ];

        return \Response::json([
            'status' => 'success',
            'message' => 'The campaign was paused',
            'campaign' => $campaign
        ], 200);
    }

    public function bounces($id)
    {

        $user = \Auth::guard('api')->user();

        $item = \Acelle\Model\Campaign::where('uid', '=', $id)
            ->first();

        // check if item exists
        if (!$item) {
            return \Response::json(array('message' => 'Campaign not found'), 404);
        }

        // authorize
        if (!$user->can('read', $item)) {
            return \Response::json(array('message' => 'Unauthorized'), 401);
        }

        $bounces = $item->bounceLogs();
        if (count($bounces)) {
            foreach ($bounces as $key => $bounce) {
                $data[$key]['email'] =  $bounce->trackingLog->subscriber->email;
            }
        }

        return \Response::json([
            'status' => 'success',
            'message' => 'The campaign was paused',
            'bouces' => $data
        ], 200);
    }

    public function opens_clicked($id)
    {


        $user = \Auth::guard('api')->user();

        $campaign = \Acelle\Model\Campaign::where('uid', '=', $id)
            ->first();

        // check if item exists
        if (!$campaign) {
            return \Response::json(array('message' => 'Campaign not found'), 404);
        }

        // authorize
        if (!$user->can('read', $campaign)) {
            return \Response::json(array('message' => 'Unauthorized'), 401);
        }

        $data['open_logs'] = $campaign->openLogs()->whereDate('open_logs.created_at', request()->get('date'))->get()->toArray();
        $data['clicked_logs'] = $campaign->clickLogsApi()->whereDate('click_logs.created_at', request()->get('date'))->get()->toArray();

        return \Response::json([
            'status' => 'success',
            'message' => 'campaign logs retrived',
            'logs' => $data
        ], 200);
    }

    public function opens_clicked_all($id)
    {


        $user = \Auth::guard('api')->user();

        $campaign = \Acelle\Model\Campaign::where('uid', '=', $id)
            ->first();

        // check if item exists
        if (!$campaign) {
            return \Response::json(array('message' => 'Campaign not found'), 404);
        }

        // authorize
        if (!$user->can('read', $campaign)) {
            return \Response::json(array('message' => 'Unauthorized'), 401);
        }

        $data['open_logs'] = $campaign->openLogs()->whereDate('open_logs.created_at', '>=', '2023-07-19')->get()->toArray();
        $data['clicked_logs'] = $campaign->clickLogsApi()->whereDate('click_logs.created_at', '>=', '2023-07-19')->get()->toArray();

        echo "here";
        die;
        return \Response::json([
            'status' => 'success',
            'message' => 'campaign logs retrived',
            'logs' => $data
        ], 200);
    }

    public function digitalocean_clean_up()
    {
        $droplets = [];
        $url = env('DO_DROPLET') . '/droplets?page=1&per_page=100';  // start with the first page
        $sending_servers = \Acelle\Model\SendingServer::where('status',"active")->wherenotnull('default_from_email')
        ->where('multi_server_linked_with',0)
        ->get();
        echo "<pre>";
        print_r($sending_servers); die;
        do {
            $response =  Http::withHeaders([
                'Authorization' => 'Bearer ' . env('DO_KEY'),
                'content-type' => 'application/json'
            ])->get($url);
            $body = $response->getBody();
            $data = json_decode($body, true);

            $droplets = array_merge($droplets, $data['droplets']);

            // Check if there's a next page.
            $url = null;
            if (isset($data['links']['pages']['next'])) {
                $parsed = parse_url($data['links']['pages']['next']);
                $url = $data['links']['pages']['next'];
            }
        } while ($url);

        

        foreach($droplets as $droplet){
            $droplet_ids[] = $droplet['id'];
        }

        $available = [];
        $unavailable = [];
        foreach($sending_servers as $sending_server){
            $options = json_decode($sending_server->options);
            $droplet_id = isset($options->identities->{$sending_server->default_from_email}->droplet_id) ? $options->identities->{$sending_server->default_from_email}->droplet_id : '';
            if(in_array($droplet_id, $droplet_ids)){
                $available[] = $sending_server->default_from_email;
            }else{
                $unavailable[] = $sending_server->default_from_email;
            }
        }

        echo "<pre>";
        echo "available";
        print_r($available);
        echo "unavailable";
        print_r($unavailable); die;
    }
}
