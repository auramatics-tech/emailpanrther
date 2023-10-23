<?php

namespace Acelle\Http\Controllers\Api;

use Acelle\Http\Controllers\CampaignController;
use Acelle\Http\Controllers\Controller;
use Acelle\Model\Campaign;
use Acelle\Model\OpenLog;
use Acelle\Model\TrackingLog;
use Acelle\Model\Subscriber;
use Acelle\Model\EmailCount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PDO;
use Carbon\Carbon;

/**
 * /api/v1/campaigns - API controller for managing campaigns.
 */
class DashboardDataController extends Controller
{

    public function getcounts(Request $request)
    {
        $domains = $request->domains;
        $data['total_sent'] = 0;
        $data['opens_count'] = 0;
        $data['clicks_count'] = 0;
        $data['unsubscribes'] = 0;
        if (count($domains)) {
            foreach ($domains as $domain) {
                $campaign = Campaign::where('from_email', $domain)->first();
                if (isset($campaign->id)) {
                    $data['total_sent'] += $campaign->deliveredCount();
                    $data['opens_count'] += $campaign->readCache('UniqOpenCount');
                    $data['clicks_count'] += $campaign->uniqueClickCount();
                    $data['unsubscribes'] += $campaign->unsubscribeCount();
                }
            }
        }

        return \Response::json($data);
    }

    public function emailsent(Request $request)
    {
        $domains = $request->domains;

        $items = array();
        $count = 0;
        $total_count = 0;
        $first_item = 1;
        $lastItem = 20;
        if (count($domains)) {
            foreach ($domains as $domain) {
                $campaign = Campaign::where('from_email', $domain)->first();
                if (isset($campaign->id)) {
                    $tracking_logs = TrackingLog::search($request, $campaign)->paginate($request->per_page);
                    $total_count += $tracking_logs->total();
                    $first_item = $tracking_logs->firstItem();
                    $lastItem = $tracking_logs->lastItem();
                    if (count($tracking_logs)) {
                        foreach ($tracking_logs as $logs) {

                            $subscriber = Subscriber::find($logs->subscriber_id);
                            $subject = $this->replace_tag($campaign->subject, $subscriber);

                            $items[$count]['to_email'] = isset($subscriber->email) ? $subscriber->email : '';
                            $items[$count]['last_event_time'] = $logs->created_at;
                            $items[$count]['subject'] = $subject;

                            $count++;
                        }
                    }
                }
            }
        }

        return \Response::json(['items' => $items, 'total_count' => $total_count, 'first_item' => $first_item, 'lastItem' => $lastItem]);
    }

    public function emailsopened(Request $request)
    {
        $domains = $request->domains;

        $items = array();
        $count = 0;
        $total_count = 0;
        $first_item = 1;
        $lastItem = 20;
        if (count($domains)) {
            foreach ($domains as $domain) {
                $campaign = Campaign::where('from_email', $domain)->first();
                if (isset($campaign->id)) {
                    $OpenLog = OpenLog::select('open_logs.created_at', 'tracking_logs.subscriber_id', 'tracking_logs.message_id')
                        ->join("tracking_logs", "open_logs.message_id", "=", "tracking_logs.message_id")
                        ->where('tracking_logs.campaign_id', '=', $campaign->id)
                        ->groupby('tracking_logs.subscriber_id')
                        ->orderBy('open_logs.created_at', $request->sort_direction)
                        ->paginate($request->per_page);
                    $total_count += $OpenLog->total();
                    $first_item = $OpenLog->firstItem();
                    $lastItem = $OpenLog->lastItem();
                    if (count($OpenLog)) {
                        foreach ($OpenLog as $logs) {

                            $subscriber = Subscriber::find($logs->subscriber_id);
                            $subject = $this->replace_tag($campaign->subject, $subscriber);

                            $items[$count]['to_email'] = isset($subscriber->email) ? $subscriber->email : '';
                            $items[$count]['opens_count'] = 1;
                            $items[$count]['phone'] = isset($subscriber->emai) ? $subscriber->getValueByTag('Phone') : '';
                            $items[$count]['last_event_time'] = $logs->created_at;
                            $items[$count]['subject'] = $subject;

                            $count++;
                        }
                    }
                }
            }
        }

        return \Response::json(['items' => $items, 'total_count' => $total_count, 'first_item' => $first_item, 'lastItem' => $lastItem]);
    }


    public function emailsclicked(Request $request)
    {
        $domains = $request->domains;

        $items = array();
        $count = 0;
        $total_count = 0;
        $first_item = 1;
        $lastItem = 20;
        if (count($domains)) {
            foreach ($domains as $domain) {
                $campaign = Campaign::where('from_email', $domain)->first();
                if (isset($campaign->id)) {
                    $ClickLog = \Acelle\Model\ClickLog::select('click_logs.created_at', 'tracking_logs.subscriber_id', 'tracking_logs.message_id')
                        ->join("tracking_logs", "click_logs.message_id", "=", "tracking_logs.message_id")
                        ->where('tracking_logs.campaign_id', '=', $campaign->id)
                        ->groupby('tracking_logs.subscriber_id')
                        ->orderBy('click_logs.created_at', $request->sort_direction)
                        ->paginate($request->per_page);

                    $total_count += $ClickLog->total();
                    $first_item = $ClickLog->firstItem();
                    $lastItem = $ClickLog->lastItem();
                    if (count($ClickLog)) {
                        foreach ($ClickLog as $logs) {

                            $subscriber = Subscriber::find($logs->subscriber_id);
                            $subject = $this->replace_tag($campaign->subject, $subscriber);

                            $items[$count]['to_email'] = isset($subscriber->email) ? $subscriber->email : '';
                            $items[$count]['clicks_count'] = 1;
                            $items[$count]['phone'] = isset($subscriber->email) ? $subscriber->getValueByTag('Phone') : '';
                            $items[$count]['last_event_time'] = $logs->created_at;
                            $items[$count]['subject'] = $subject;

                            $count++;
                        }
                    }
                }
            }
        }

        return \Response::json(['items' => $items, 'total_count' => $total_count, 'first_item' => $first_item, 'lastItem' => $lastItem]);
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

    public function datecount(Request $request)
    {
        $campaigns = Campaign::whereIn('from_email', $request->domains)->pluck('id')->toArray();
        $endDate = Carbon::today();
        $startDate = Carbon::today()->subMonth();
        while ($startDate->lte($endDate)) {
            $date = $startDate->toDateString();
            $data[$date]['count'] = EmailCount::whereIn('campaign_id', $campaigns)->where('date',  $date)->sum('count');
            $data[$date]['count_open'] = EmailCount::whereIn('campaign_id', $campaigns)->where('date',  $date)->sum('count_open');
            $data[$date]['count_click'] = EmailCount::whereIn('campaign_id', $campaigns)->where('date',  $date)->sum('count_click');

            $startDate->addDay();
        }


        return \Response::json($data);
    }
}
