<?php

namespace Acelle\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LaravelLog;
use Gate;
use Validator;
use Illuminate\Validation\ValidationException;
use Acelle\Library\StringHelper;
use Acelle\Jobs\ExportCampaignLog;
use Acelle\Model\Template;
use Acelle\Model\TrackingLog;
use Acelle\Model\Setting;
use Acelle\Model\Subscriber;
use Acelle\Model\Unsubscriber;
use Acelle\Model\UnsubscribeLog;
use Acelle\Model\Campaign;
use Acelle\Model\IpLocation;
use Acelle\Model\ClickLog;
use Acelle\Model\OpenLog;
use Acelle\Model\BounceLog;
use Acelle\Model\Blacklist;
use Acelle\Model\TemplateCategory;
use Acelle\Model\JobMonitor;
use Acelle\Model\CampaignSteps;
use Acelle\Model\CampaignSendingServer;
use Acelle\Model\CampaignsListsSegment;
use Acelle\Model\CampaignStepsSettings;
use Acelle\Model\CampaignStepsVariant;
use Acelle\Model\ConditionLog;
use DB;
use Exception;
use Carbon\Carbon;
use Log;
use Illuminate\Support\Facades\Http;
use PDO;
use GuzzleHttp\Client;


class CampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $customer = $request->user()->customer;
        $campaigns = $customer->campaigns();

        return view('campaigns.index', [
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function listing(Request $request)
    {
        // echo date('Y-m-d H:i:s');
        $customer = $request->user()->customer;

        $campaigns = $customer->campaigns()
            ->where('linked_camp_main', 1)
            ->search($request->keyword)
            ->filter($request)
            ->orderBy($request->sort_order, $request->sort_direction)
            ->paginate($request->per_page);

        return view('campaigns._list', [
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $customer = $request->user()->customer;
        $campaign = new Campaign([
            'track_open' => true,
            'track_click' => true,
            'sign_dkim' => true,
        ]);

        // authorize
        if (\Gate::denies('create', $campaign)) {
            return $this->noMoreItem();
        }

        $campaign->name = trans('messages.untitled');
        $campaign->customer_id = $customer->id;
        $campaign->status = Campaign::STATUS_NEW;
        $campaign->type = $request->type;
        $campaign->linked_camp_main  = 1;
        $campaign->save();
        $campaign->linked_campaigns = json_encode([$campaign->id]);
        $campaign->save();

        return redirect()->action('CampaignController@recipients', ['uid' => $campaign->uid]);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $campaign = Campaign::findByUid($id);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        // Trigger the CampaignUpdate event to update the campaign cache information
        // The second parameter of the constructor function is false, meanining immediate update
        event(new \Acelle\Events\CampaignUpdated($campaign));

        if ($campaign->status == 'new') {
            return redirect()->action('CampaignController@edit', ['uid' => $campaign->uid]);
        } else {
            return redirect()->action('CampaignController@overview', ['uid' => $campaign->uid]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $campaign = Campaign::findByUid($id);

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        // Check step and redirect
        if ($campaign->step() == 0) {
            return redirect()->action('CampaignController@recipients', ['uid' => $campaign->uid]);
        } elseif ($campaign->step() == 1) {
            return redirect()->action('CampaignController@setup', ['uid' => $campaign->uid]);
        } elseif ($campaign->step() == 2) {
            return redirect()->action('CampaignController@template', ['uid' => $campaign->uid]);
        } elseif ($campaign->step() == 3) {
            return redirect()->action('CampaignController@confirm', ['uid' => $campaign->uid]);
            // return redirect()->action('CampaignController@schedule', ['uid' => $campaign->uid]);
        } elseif ($campaign->step() >= 4) {
            return redirect()->action('CampaignController@confirm', ['uid' => $campaign->uid]);
        }
    }

    /**
     * Recipients.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function recipients(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);
        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        // Get rules and data
        $rules = $campaign->recipientsRules($request->all());
        $campaign->fillRecipients($request->all());

        if (!empty($request->old())) {
            $rules = $campaign->recipientsRules($request->old());
            $campaign->fillRecipients($request->old());
        }

        if ($request->isMethod('post')) {
            $campaign->random_order = isset($request->random_order) ? $request->random_order : 0;
            $campaign->server_type = isset($request->server_type) ? $request->server_type : 'smtp';
            $campaign->save();
            // Check validation
            $this->validate($request, $rules);

            $campaign->saveRecipients($request->all());


            // Trigger the CampaignUpdate event to update the campaign cache information
            // The second parameter of the constructor function is false, meanining immediate update
            event(new \Acelle\Events\CampaignUpdated($campaign));

            // redirect to the next step
            return redirect()->action('CampaignController@setup', ['uid' => $campaign->uid]);
        }

        return view('campaigns.recipients', [
            'campaign' => $campaign,
            'rules' => $rules,
        ]);
    }

    /**
     * Campaign setup.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function setup(Request $request)
    {
        $customer = $request->user()->customer;
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        $current_linked = json_decode($campaign->linked_campaigns);
        $campaign->from_name = !empty($campaign->from_name) ? $campaign->from_name : $campaign->defaultMailList->from_name;
        $campaign->from_email = !empty($campaign->from_email) ? $campaign->from_email : $campaign->defaultMailList->from_email;

        // Get old post values
        if ($request->old()) {
            $campaign->fillAttributes($request->old());
        }

        // validate and save posted data
        if ($request->isMethod('post')) {
            $deleted_linked = array();

            // Check validation
            list($linked_campaigns, $new_camp) = $this->create_campaigns_attach_server($campaign, $request, ($campaign->linked_campaigns) ? json_decode($campaign->linked_campaigns) : []);

            if (count($linked_campaigns)) {
                foreach ($linked_campaigns as $l_c) {
                    if (in_array($l_c, $new_camp)) {
                        $camp = Campaign::find($l_c);
                        $camp->linked_campaigns = json_encode($new_camp);
                        $camp->group_id = $campaign->group_id;
                        $camp->random_order = $campaign->random_order;
                        $camp->save();

                        // check if steps exists
                        $link_step = CampaignSteps::where([
                            'campaign_id' => $camp->id
                        ])->first();
                        if (!isset($link_step->id)) {
                            $camp->copy_templates($camp, $campaign);
                        }
                        $camp->group_id = $campaign->group_id;
                        $camp->random_order = $campaign->random_order;
                        $camp->save();
                    } else {
                        $deleted_linked[] = $l_c;
                        $camp = Campaign::find($l_c);
                        //delete sending server
                        CampaignSendingServer::where('campaign_id', $l_c)->delete();
                        $camp->deleteAndCleanup();
                    }
                }
            }

            return redirect()->action('CampaignController@template', ['uid' => $campaign->uid]);
        }
        $rules = $campaign->rules();

        $sending_servers = [];
        if ($campaign->linked_campaigns) {
            $linked_campaigns = json_decode($campaign->linked_campaigns);
            if (count($linked_campaigns)) {
                foreach ($linked_campaigns as $l_camp) {
                    $camp = Campaign::find($l_camp);
                    $sending_servers[] = $camp->from_email;
                }
            }
        }

        return view('campaigns.setup', [
            'campaign' => $campaign,
            'rules' => $campaign->rules(),
            'linked_sending_servers' => $sending_servers
        ]);
    }

    protected function create_campaigns_attach_server($campaign_main, $request, $linked_campaigns)
    {
        if (count($request->from_email)) {
            foreach ($request->from_email as $key =>  $from_emails) {

                $customer = $request->user()->customer;
                $sending_server = \Acelle\Model\SendingServer::find($from_emails);

                if ($key != 0) {

                    // check if campaign is already linked
                    $o_campaign = Campaign::where('from_email', $sending_server->name)->pluck('id')->toArray();
                    $result = array_intersect($linked_campaigns, $o_campaign);
                    if (count($result) && count($linked_campaigns)) {
                        foreach ($result as $o_c) {
                            $campaign = Campaign::find($o_c);
                            $campaign->template_id = $campaign_main->template_id;
                            $campaign->save();
                        }
                    } else {
                        $campaign = $this->new_camp($campaign_main, $customer);
                    }
                } else {
                    $campaign = $campaign_main;
                    $campaign->linked_camp_main =  1;
                }

                // Fill values
                // get tracking_domain
                if ($campaign->server_type == 'smtp') {
                    $from = explode('@', $sending_server->name);
                    $tracking_domain = \Acelle\Model\TrackingDomain::where('name', $from[1])->first();
                } else {
                    $tracking_domain = \Acelle\Model\TrackingDomain::where('name', $sending_server->name)->first();
                }

                $data = $request->all();
                $data['tracking_domain_uid'] = isset($tracking_domain->id) ? $tracking_domain->uid : '';
                $request->tracking_domain_uid = $data['tracking_domain_uid'];

                $camp_sending_servers = [];

                if (isset($data['sending_servers']))
                    unset($data['sending_servers']);

                if ($campaign->server_type == 'smtp') {
                    $data['sending_servers'][$sending_server->uid]['check'] = true;
                    $camp_sending_servers[]['id'] = $sending_server->id;
                    $request->merge($data);
                } elseif ($campaign->server_type == 'multi-smtp') {
                    $main_sending_server = $sending_server;
                    $servers = \Acelle\Model\SendingServer::where(['type' => 'multi-smtp', 'multi_server_linked_with' => $main_sending_server->id])->get();
                    if (count($servers)) {
                        foreach ($servers as $server) {
                            $data['sending_servers'][$server->uid]['check'] = true;
                            $camp_sending_servers[]['id'] = $server->id;
                            $request->merge($data);
                        }
                    }
                }
                $campaign->fillAttributes($data);

                $campaign->template_id = $campaign_main->template_id;
                $campaign->from_email = $sending_server->name;
                $campaign->reply_to = $sending_server->name;
                $campaign->group_id = $campaign_main->group_id;
                $campaign->random_order = $campaign_main->random_order;
                $campaign->save();

                $campaign_mail_lists = $campaign_main->getListsSegments();
                if (count($campaign_mail_lists)) {
                    foreach ($campaign_mail_lists as $mail_list) {
                        // check if list is there
                        $list_seg = CampaignsListsSegment::where([
                            'campaign_id' => $campaign->id,
                            'mail_list_id' => $mail_list->mail_list_id,
                            'segment_id' => $mail_list->segment_id,
                        ])->first();
                        if (!isset($list_seg->id))
                            $mail_list_data[] = [
                                'campaign_id' => $campaign->id,
                                'mail_list_id' => $mail_list->mail_list_id,
                                'segment_id' => $mail_list->segment_id,
                            ];
                    }
                }

                if (isset($mail_list_data) && count($mail_list_data))
                    CampaignsListsSegment::insert($mail_list_data);

                // Log
                $campaign->log('created', $customer);
                // die;

                // For sending servers
                // if (isset($request->sending_servers)) {
                $campaign->updateSendingServers($camp_sending_servers);
                // }

                if (!in_array($campaign->id, $linked_campaigns))
                    $linked_campaigns[] = $campaign->id;

                $new_camp[] = $campaign->id;
            }
        }
        // die;

        return [$linked_campaigns, $new_camp];
    }

    protected function new_camp($campaign_main, $customer)
    {
        $campaign = new Campaign([
            'track_open' => true,
            'track_click' => true,
            'sign_dkim' => true,
        ]);

        // authorize
        if (\Gate::denies('create', $campaign)) {
            return $this->noMoreItem();
        }

        $campaign->subject = $campaign_main->subject;
        $campaign->plain = $campaign_main->plain;
        $campaign->default_mail_list_id = $campaign_main->default_mail_list_id;
        $campaign->template_id = $campaign_main->template_id;
        $campaign->name = trans('messages.untitled');
        $campaign->customer_id = $customer->id;
        $campaign->status = Campaign::STATUS_NEW;
        $campaign->type = 'regular';
        $campaign->server_type = $campaign_main->server_type;
        $campaign->group_id = $campaign_main->group_id;
        $campaign->random_order = $campaign_main->random_order;
        $campaign->save();

        return $campaign;
    }

    /**
     * Template.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function template(Request $request)
    {
        $customer = $request->user()->customer;
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        if ($campaign->type == 'plain-text') {
            return redirect()->action('CampaignController@plain', ['uid' => $campaign->uid]);
        }

        // check if campagin does not have template
        if (!$campaign->template) {
            return redirect()->action('CampaignController@templateCreate', ['uid' => $campaign->uid]);
        }

        if (count($campaign->steps) == 0) {
            // create step if non yet
            $campaign->create_first_step();
            $campaign->fresh();

            $campaign = Campaign::findByUid($request->uid);
        }

        $template = \Acelle\Model\Template::findByUid('6037a2c3d7fa1');
        $campaign->setTemplate($template);

        return view('campaigns.template.index', [
            'campaign' => $campaign,
            'spamscore' => Setting::isYes('spamassassin.enabled'),
        ]);
    }

    /**
     * Create template.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function templateCreate(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        if ($campaign->linked_campaigns) {
            $linked_campaigns = json_decode($campaign->linked_campaigns);
            if (count($linked_campaigns)) {
                foreach ($linked_campaigns as $linked_campaign) {
                    $camp = Campaign::find($linked_campaign);
                    $this->linkTemplate($camp);
                }
            }
        }

        return view('campaigns.template.index', [
            'campaign' => $campaign,
        ]);
    }

    protected function linkTemplate($campaign)
    {
        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }
        if (count($campaign->steps) == 0) {
            // create step if non yet
            $campaign->create_first_step();
            $campaign->fresh();

            $campaign = Campaign::findByUid($campaign->uid);
        }
        $template = \Acelle\Model\Template::findByUid('6037a2c3d7fa1');
        $campaign->setTemplate($template);
    }

    /**
     * Create template from layout.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function templateLayout(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        if ($request->isMethod('post')) {
            $template = \Acelle\Model\Template::findByUid($request->template);
            $campaign->setTemplate($template);

            // return redirect()->action('CampaignController@templateEdit', $campaign->uid);
            return response()->json([
                'status' => 'success',
                'message' => trans('messages.campaign.theme.selected'),
                'url' => action('CampaignController@templateBuilderSelect', $campaign->uid),
            ]);
        }

        // default tab
        if ($request->from != 'mine' && !$request->category_uid) {
            $request->category_uid = TemplateCategory::first()->uid;
        }

        return view('campaigns.template.layout', [
            'campaign' => $campaign
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function templateLayoutList(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // from
        if ($request->from == 'mine') {
            $templates = $request->user()->customer->templates()->email();
        } elseif ($request->from == 'gallery') {
            $templates = Template::shared()->email();
        } else {
            $templates = Template::shared()->email()
                ->orWhere('customer_id', '=', $request->user()->customer->id);
        }

        $templates = $templates->notPreserved()->search($request->keyword);

        // category id
        if ($request->category_uid) {
            $templates = $templates->categoryUid($request->category_uid);
        }

        $templates = $templates->orderBy($request->sort_order, $request->sort_direction)
            ->paginate($request->per_page);

        return view('campaigns.template.layoutList', [
            'campaign' => $campaign,
            'templates' => $templates,
        ]);
    }

    /**
     * Select builder for editing template.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function templateBuilderSelect(Request $request, $uid)
    {
        $campaign = Campaign::findByUid($uid);

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.template.templateBuilderSelect', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Edit campaign template.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function templateEdit(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        // save campaign html
        if ($request->isMethod('post')) {
            $rules = array(
                'content' => 'required',
            );

            $this->validate($request, $rules);

            // template extra validation by plan (unsubscribe URL for example)
            // UGLY code here, @todo: find a good place to handle this type of validation
            $plan = $request->user()->customer->getCurrentActiveSubscription()->plan;
            if ($plan->getOption('unsubscribe_url_required') == 'yes' && Setting::isYes('campaign.enforce_unsubscribe_url_check')) {
                if (strpos($request->content, '{UNSUBSCRIBE_URL}') === false) {
                    return response()->json(['message' => trans('messages.template.validation.unsubscribe_url_required')], 400);
                }
            }

            $campaign->setTemplateContent($request->content);
            $campaign->save();

            // update plain
            $campaign->updatePlainFromHtml();

            return response()->json([
                'status' => 'success',
            ]);
        }

        return view('campaigns.template.edit', [
            'campaign' => $campaign,
            'list' => $campaign->defaultMailList,
            'templates' => $request->user()->customer->getBuilderTemplates(),
        ]);
    }

    /**
     * Campaign html content.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function templateContent(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.template.content', [
            'content' => $campaign->template->content,
        ]);
    }

    /**
     * Upload template.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function templateUpload(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        // validate and save posted data
        if ($request->isMethod('post')) {
            $campaign->uploadTemplate($request);

            // return redirect()->action('CampaignController@template', $campaign->uid);
            return response()->json([
                'status' => 'success',
                'message' => trans('messages.campaign.template.uploaded'),
                'url' => action('CampaignController@templateBuilderSelect', $campaign->uid),
            ]);
        }

        return view('campaigns.template.upload', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Choose an existed template.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function plain(Request $request)
    {
        $user = $request->user();
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        // validate and save posted data
        if ($request->isMethod('post')) {
            // Check validation
            $this->validate($request, ['plain' => 'required']);

            // save campaign plain text
            $campaign->plain = $request->plain;
            $campaign->save();

            return redirect()->action('CampaignController@schedule', ['uid' => $campaign->uid]);
        }

        return view('campaigns.plain', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Template preview iframe.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function templateIframe(Request $request)
    {
        $user = $request->user();
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.preview', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Schedule.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function schedule(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);
        $currentTimezone = $campaign->customer->getTimezone();

        // check step
        if ($campaign->step() < 3) {
            return redirect()->action('CampaignController@template', ['uid' => $campaign->uid]);
        }

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        // validate and save posted data
        if ($request->isMethod('post')) {
            if ($request->send_now == 'yes') {
                $campaign->run_at = null;
            } else {
                $runAtStr = $request->delivery_date . ' ' . $request->delivery_time;
                $runAt = Carbon::createFromFormat('Y-m-d H:i', $runAtStr, $currentTimezone);
                $campaign->run_at = $runAt; // store in UTC
            }

            $campaign->save();
            return redirect()->action('CampaignController@confirm', ['uid' => $campaign->uid]);
        }

        // Get the run_at datetime in current customer timezone
        $runAt = is_null($campaign->run_at) ? Carbon::now($currentTimezone) : $campaign->run_at;
        $runAt->timezone($currentTimezone);

        $delivery_date = $runAt->format('Y-m-d');
        $delivery_time = $runAt->format('H:i');

        $rules = array(
            'delivery_date' => 'required',
            'delivery_time' => 'required',
        );

        // Get old post values
        if (null !== $request->old()) {
            $campaign->fill($request->old());
        }

        return view('campaigns.schedule', [
            'campaign' => $campaign,
            'rules' => $rules,
            'delivery_date' => $delivery_date,
            'delivery_time' => $delivery_time,
        ]);
    }

    /**
     * Cofirm.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function confirm(Request $request)
    {
        $customer = $request->user()->customer;
        $campaign = Campaign::findByUid($request->uid);

        if ($campaign->linked_campaigns) {
            $linked_campaigns = json_decode($campaign->linked_campaigns);
            if (count($linked_campaigns)) {
                foreach ($linked_campaigns as $linked_campaign) {
                    $l_campaign = Campaign::find($linked_campaign);
                    $l_campaign->template_id = $campaign->template_id;
                    $l_campaign->save();
                    // check step
                    if ($l_campaign->step() < 4) {
                        return redirect()->action('CampaignController@template', ['uid' => $campaign->uid]);
                    }

                    // authorize
                    if (\Gate::denies('update', $l_campaign)) {
                        return $this->notAuthorized();
                    }

                    try {
                        $score = $l_campaign->score();
                    } catch (\Exception $e) {
                        $score = null;
                    }
                    // get step 1 data
                    $steps = $l_campaign->steps;
                    if (isset($steps[0]->variants[0]->subject)) {
                        $l_campaign->subject = $steps[0]->variants[0]->subject;
                        $l_campaign->setTemplateContent($steps[0]->variants[0]->content);
                        $l_campaign->save();
                    }
                    // validate and save posted data
                    if ($request->isMethod('post') && $l_campaign->step() >= 5) {
                        // UGLY CODE
                        $plan = $customer->getCurrentActiveSubscription()->plan;
                        if ($plan->getOption('unsubscribe_url_required') == 'yes' && Setting::isYes('campaign.enforce_unsubscribe_url_check')) {
                            if (strpos($l_campaign->getTemplateContent(), '{UNSUBSCRIBE_URL}') === false) {
                                $request->session()->flash('alert-error', trans('messages.template.validation.unsubscribe_url_required'));
                                return view('campaigns.confirm', [
                                    'campaign' => $l_campaign,
                                    'score' => $score,
                                ]);
                            }
                        }

                        // Save campaign
                        // @todo: check campaign status before requeuing. Otherwise, several jobs shall be created and campaign will get sent several times
                        $l_campaign->schedule();

                        // Log
                        $l_campaign->log('started', $customer);
                    }
                }
            }
        }

        if ($request->isMethod('post')) {
            return redirect()->action('CampaignController@index');
        }

        return view('campaigns.confirm', [
            'campaign' => $campaign,
            'score' => $score,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {
        if (isSiteDemo()) {
            return response()->json(["message" => trans('messages.operation_not_allowed_in_demo')], 404);
        }

        $customer = $request->user()->customer;

        if (isSiteDemo()) {
            echo trans('messages.operation_not_allowed_in_demo');

            return;
        }

        if (!is_array($request->uids)) {
            $request->uids = explode(',', $request->uids);
        }

        $campaigns = Campaign::whereIn('uid', $request->uids);

        foreach ($campaigns->get() as $campaign) {
            // check if there are linked campaigns
            $linked_campaigns = json_decode($campaign->linked_campaigns);
            foreach ($linked_campaigns as $camp) {
                $l_campaign = Campaign::find($camp);
                // authorize
                if (\Gate::allows('delete', $campaign)) {
                    $l_campaign->deleteAndCleanup();
                }
            }
        }

        // Redirect to my lists page
        echo trans('messages.campaigns.deleted');
    }

    /**
     * Campaign overview.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function overview(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // Trigger the CampaignUpdate event to update the campaign cache information
        // The second parameter of the constructor function is false, meanining immediate update
        event(new \Acelle\Events\CampaignUpdated($campaign));

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.overview', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Campaign links.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function links(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);
        $links = $campaign->clickLogs()
            ->select(
                'click_logs.url',
                DB::raw('count(*) AS clickCount'),
                DB::raw(sprintf('max(%s) AS lastClick', table('click_logs.created_at')))
            )->groupBy('click_logs.url')->get();

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.links', [
            'campaign' => $campaign,
            'links' => $links,
        ]);
    }

    /**
     * 24-hour chart.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function chart24h(Request $request)
    {
        $currentTimezone = $request->user()->customer->getTimezone();
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $result = [
            'columns' => [],
            'opened' => [],
            'clicked' => [],
        ];

        $hours = [];

        // columns
        for ($i = 23; $i >= 0; --$i) {
            $time = Carbon::now()->timezone($currentTimezone)->subHours($i);
            $result['columns'][] = $time->format('h') . ':00 ' . $time->format('A');
            $hours[] = $time->format('H');
        }

        // 24h collection
        $openData24h = $campaign->openUniqHours(Carbon::now()->timezone($currentTimezone)->subHours(24), Carbon::now()->timezone($currentTimezone));
        $clickData24h = $campaign->clickHours(Carbon::now()->timezone($currentTimezone)->subHours(24), Carbon::now()->timezone($currentTimezone));

        // data
        foreach ($hours as $hour) {
            $num = isset($openData24h[$hour]) ? count($openData24h[$hour]) : 0;
            $result['opened'][] = $num;

            $num = isset($clickData24h[$hour]) ? count($clickData24h[$hour]) : 0;
            $result['clicked'][] = $num;
        }

        return response()->json($result);
    }

    /**
     * Chart.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function chart(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $result = [
            [
                'name' => trans('messages.recipients'),
                'value' => $campaign->readCache('SubscriberCount', 0),
            ],
            [
                'name' => trans('messages.delivered'),
                'value' => $campaign->deliveredCount(),
            ],
            [
                'name' => trans('messages.failed'),
                'value' => $campaign->failedCount(),
            ],
            [
                'name' => trans('messages.Open'),
                'value' => $campaign->openUniqCount(),
            ],
            [
                'name' => trans('messages.Click'),
                'value' => $campaign->uniqueClickCount(),
            ],
            [
                'name' => trans('messages.Bounce'),
                'value' => $campaign->bounceCount(),
            ],
            [
                'name' => trans('messages.report'),
                'value' => $campaign->feedbackCount(),
            ],
            [
                'name' => trans('messages.unsubscribe'),
                'value' => $campaign->unsubscribeCount(),
            ],
        ];

        return response()->json($result);
    }

    /**
     * Chart Country.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function chartCountry(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $result = [
            'data' => [],
        ];

        // create data
        $total = $campaign->uniqueOpenCount();
        $count = 0;
        foreach ($campaign->topOpenCountries()->get() as $location) {
            $country_name = (!empty($location->country_name) ? $location->country_name : trans('messages.unknown'));
            $result['data'][] = ['value' => $location->aggregate, 'name' => $country_name];
            $count += $location->aggregate;
        }

        // Others
        if ($total > $count) {
            $result['data'][] = ['value' => $total - $count, 'name' => trans('messages.others')];
        }

        usort($result['data'], function ($a, $b) {
            return strcmp($a['value'], $b['value']);
        });
        $result['data'] = array_reverse($result['data']);

        return response()->json($result);
    }

    /**
     * Chart Country by clicks.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function chartClickCountry(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $result = [
            'data' => [],
        ];

        // create data
        $datas = [];
        $total = $campaign->clickCount();
        $count = 0;
        foreach ($campaign->topClickCountries()->get() as $location) {
            $result['data'][] = ['value' => $location->aggregate, 'name' => $location->country_name];
            $count += $location->aggregate;
        }

        // others
        if ($total > $count) {
            $result['data'][] = ['value' => $total - $count, 'name' => trans('messages.others')];
        }

        usort($result['data'], function ($a, $b) {
            return strcmp($a['value'], $b['value']);
        });
        $result['data'] = array_reverse($result['data']);

        return response()->json($result);
    }

    /**
     * 24-hour quickView.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function quickView(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns._quick_view', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Select2 campaign.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function select2(Request $request)
    {
        $data = ['items' => [], 'more' => true];

        $data['items'][] = ['id' => 0, 'text' => trans('messages.all')];
        foreach (Campaign::getAll()->get() as $campaign) {
            $data['items'][] = ['id' => $campaign->uid, 'text' => $campaign->name];
        }

        echo json_encode($data);
    }

    /**
     * Tracking when open.
     */
    public function open(Request $request)
    {
        try {
            // Log::info(json_encode($request->all()));
            // Record open log
            $openLog = OpenLog::createFromRequest($request);

            // Execute open callbacks registered for the campaign
            if ($openLog->trackingLog && $openLog->trackingLog->campaign) {
                $openLog->trackingLog->campaign->queueOpenCallbacks($openLog);
            }

            // check steps condition
            $step_condition_open = CampaignStepsSettings::where(['campaign_step_id' => $openLog->trackingLog->campaign_step_id, 'condition' => 'opens_email'])->first();
            if (isset($step_condition_open->id)) {
                $current_step = CampaignSteps::find($openLog->trackingLog->campaign_step_id);
                $next_step = CampaignSteps::where(['campaign_id' => $current_step->campaign_id, 'step_number' => $current_step->step_number + 1])->first();
                $variant = $next_step->random_variant();
                // check if log exists
                $log_check = ConditionLog::where([
                    'subscriber_id' => $openLog->trackingLog->subscriber_id,
                    'campaign_id' => $openLog->trackingLog->campaign_id,
                    'step_id' => $next_step->id
                ])->first();
                if (!isset($log_check->id)) {
                    $conditionlog = new ConditionLog();
                    $conditionlog->subscriber_id = $openLog->trackingLog->subscriber_id;
                    $conditionlog->campaign_id = $openLog->trackingLog->campaign_id;
                    $conditionlog->variant_id = $variant->id;
                    $conditionlog->step_id = $next_step->id;
                    $conditionlog->sent = 0;
                }

                $campaign = Campaign::find($current_step->campaign_id);
                if ($step_condition_open->condition_purpose == 'skip_wait_time') {
                    $conditionlog->runs_at = NULL;

                    $campaign->scheduleStep();
                } else {
                    $next_step_time = $step_condition_open->wait_time;
                    $currentTimezone = $campaign->customer->getTimezone();
                    $runAtStr = date('Y-m-d H:i', strtotime("+$next_step_time hours"));
                    //$runAtStr = date('Y-m-d H:i', strtotime("+2 mins"));
                    // Log::info('Next step at ' . $runAtStr);
                    $runAt = Carbon::createFromFormat('Y-m-d H:i', $runAtStr, $currentTimezone);
                    $conditionlog->runs_at = $runAt;

                    $campaign->scheduleStepChangeWaitTime($runAt);
                }
                $conditionlog->save();
            }
        } catch (\Exception $ex) {
            // do nothing
        }

        return response()->file(public_path('images/transparent.gif'));
    }

    /**
     * Tracking when click link.
     */
    public function click(Request $request)
    {
        list($url, $log) = ClickLog::createFromRequest($request);
        // Log::info($request->url);
        if ($log && $log->trackingLog && $log->trackingLog->campaign) {
            // Log::info($url);
            $log->trackingLog->campaign->queueClickCallbacks($log);
        }


        // Log::info($url);

        return response()->json(['url' => $url]);
    }

    /**
     * Unsubscribe url.
     */
    public function unsubscribe(Request $request)
    {
        $subscriber = Subscriber::findByUid($request->subscriber);
        $message_id = StringHelper::base64UrlDecode($request->message_id);

        if (is_null($subscriber)) {
            LaravelLog::error('Subscriber does not exist');
            return view('somethingWentWrong', ['message' => trans('subscriber.invalid')]);
        }

        $unsubscriber = Unsubscriber::where(['email' => $subscriber->email, 'campaign_id' => $request->campaign_id])->first();

        if (isset($unsubscriber->id)) {
            return response()->json(['email' => $subscriber->email]);
            return view('notice', ['message' => trans('messages.you_are_already_unsubscribed')]);
        }

        // User Tracking Information
        $trackingInfo = [
            'message_id' => $message_id,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        ];

        // GeoIP information
        $location = IpLocation::add($request->ip());
        if (!is_null($location)) {
            $trackingInfo['ip_address'] = $location->ip_address;
        }

        $campaign = Campaign::findByUid($request->campaign_id);

        if (isset($campaign->id)) {
            $unsubscriber = new Unsubscriber();
            $unsubscriber->message_id = $message_id;
            $unsubscriber->email = $subscriber->email;
            $unsubscriber->subscriber_id = $subscriber->id;
            $unsubscriber->campaign_id = $campaign->id;
            $unsubscriber->group_id = $campaign->group_id;
            $unsubscriber->ip_address = isset($trackingInfo['ip_address']) ? $trackingInfo['ip_address'] : '';
            $unsubscriber->save();
        }

        $UnsubscribeLog = new UnsubscribeLog();
        $UnsubscribeLog->message_id = $message_id;
        $UnsubscribeLog->ip_address = isset($trackingInfo['ip_address']) ? $trackingInfo['ip_address'] : '';
        $UnsubscribeLog->subscriber_id = $subscriber->id;
        $UnsubscribeLog->user_agent = isset($trackingInfo['user_agent']) ? $trackingInfo['user_agent'] : '';
        $UnsubscribeLog->save();

        return response()->json(['email' => $subscriber->email]);
    }

    /**
     * Tracking logs.
     */
    public function trackingLog(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $items = $campaign->trackingLogs();

        return view('campaigns.tracking_log', [
            'items' => $items,
            'campaign' => $campaign,
        ]);
    }

    /**
     * Tracking logs ajax listing.
     */
    public function trackingLogListing(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);


        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        if ($campaign->linked_campaigns) {
            $linked_campaigns = json_decode($campaign->linked_campaigns);
            if (count($linked_campaigns)) {
                $items = TrackingLog::search($request, $linked_campaigns)->paginate($request->per_page);
            } else {
                $items = TrackingLog::search($request, $campaign)->paginate($request->per_page);
            }
        } else {
            $items = TrackingLog::search($request, $campaign)->paginate($request->per_page);
        }

        return view('campaigns.tracking_logs_list', [
            'items' => $items,
            'campaign' => $campaign,
        ]);
    }

    /**
     * Download tracking logs.
     */
    public function trackingLogDownload(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $logtype = $request->input('logtype');

        $job = new ExportCampaignLog($campaign, $logtype);
        $monitor = $campaign->dispatchWithMonitor($job);

        return view('campaigns.download_tracking_log', [
            'campaign' => $campaign,
            'job' => $monitor,
        ]);
    }

    /**
     * Tracking logs export progress.
     */
    public function trackingLogExportProgress(Request $request)
    {
        $job = JobMonitor::findByUid($request->uid);

        $progress = $job->getJsonData();
        $progress['status'] = $job->status;
        $progress['error'] = $job->error;
        $progress['download'] = action('CampaignController@download', ['uid' => $job->uid]);

        return response()->json($progress);
    }

    /**
     * Actually download.
     */
    public function download(Request $request)
    {
        $job = JobMonitor::findByUid($request->uid);
        $path = $job->getJsonData()['path'];
        return response()->download($path)->deleteFileAfterSend(true);
    }

    /**
     * Bounce logs.
     */
    public function bounceLog(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $items = $campaign->bounceLogs();

        return view('campaigns.bounce_log', [
            'items' => $items,
            'campaign' => $campaign,
        ]);
    }

    /**
     * Bounce logs listing.
     */
    public function bounceLogListing(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $items = \Acelle\Model\BounceLog::search($request, $campaign)->paginate($request->per_page);

        return view('campaigns.bounce_logs_list', [
            'items' => $items,
            'campaign' => $campaign,
        ]);
    }

    /**
     * FBL logs.
     */
    public function feedbackLog(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $items = $campaign->openLogs();

        return view('campaigns.feedback_log', [
            'items' => $items,
            'campaign' => $campaign,
        ]);
    }

    /**
     * FBL logs listing.
     */
    public function feedbackLogListing(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $items = \Acelle\Model\FeedbackLog::search($request, $campaign)->paginate($request->per_page);

        return view('campaigns.feedback_logs_list', [
            'items' => $items,
            'campaign' => $campaign,
        ]);
    }

    /**
     * Open logs.
     */
    public function openLog(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $items = $campaign->openLogs();

        return view('campaigns.open_log', [
            'items' => $items,
            'campaign' => $campaign,
        ]);
    }

    /**
     * Open logs listing.
     */
    public function openLogListing(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $items = \Acelle\Model\OpenLog::search($request, $campaign)->paginate($request->per_page);

        return view('campaigns.open_log_list', [
            'items' => $items,
            'campaign' => $campaign,
        ]);
    }

    /**
     * Click logs.
     */
    public function clickLog(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $items = $campaign->clickLogs();

        return view('campaigns.click_log', [
            'items' => $items,
            'campaign' => $campaign,
        ]);
    }

    /**
     * Click logs listing.
     */
    public function clickLogListing(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $items = \Acelle\Model\ClickLog::search($request, $campaign)->paginate($request->per_page);

        return view('campaigns.click_log_list', [
            'items' => $items,
            'campaign' => $campaign,
        ]);
    }

    /**
     * Unscubscribe logs.
     */
    public function unsubscribeLog(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $items = $campaign->unsubscribeLogs();

        return view('campaigns.unsubscribe_log', [
            'items' => $items,
            'campaign' => $campaign,
        ]);
    }

    /**
     * Unscubscribe logs listing.
     */
    public function unsubscribeLogListing(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $items = \Acelle\Model\UnsubscribeLog::search($request, $campaign)->paginate($request->per_page);

        return view('campaigns.unsubscribe_logs_list', [
            'items' => $items,
            'campaign' => $campaign,
        ]);
    }

    /**
     * Open map.
     */
    public function openMap(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.open_map', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Delete confirm message.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteConfirm(Request $request)
    {
        $lists = Campaign::whereIn(
            'uid',
            is_array($request->uids) ? $request->uids : explode(',', $request->uids)
        );

        return view('campaigns.delete_confirm', [
            'lists' => $lists,
        ]);
    }

    /**
     * Pause the specified campaign.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function pause(Request $request)
    {
        $customer = $request->user()->customer;
        $campaigns = Campaign::whereIn(
            'uid',
            is_array($request->uids) ? $request->uids : explode(',', $request->uids)
        );

        foreach ($campaigns->get() as $campaign) {

            if (\Gate::allows('pause', $campaign)) {
                $linked_campaigns = json_decode($campaign->linked_campaigns);
                foreach ($linked_campaigns as $camp) {
                    $l_camp = Campaign::find($camp);
                    $l_camp->pause();

                    // Log
                    $l_camp->log('paused', $customer);
                }
            }
        }

        //
        return response()->json([
            'status' => 'success',
            'message' => trans('messages.campaigns.paused'),
        ]);
    }

    /**
     * Pause the specified campaign.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function restart(Request $request)
    {
        $customer = $request->user()->customer;
        if (!is_array($request->uids)) {
            $request->uids = explode(',', $request->uids);
        }

        $items = Campaign::whereIn('uid', $request->uids);

        foreach ($items->get() as $item) {

            if (\Gate::allows('restart', $item)) {
                $linked_campaigns = json_decode($item->linked_campaigns);
                foreach ($linked_campaigns as $camp) {
                    $l_camp = Campaign::find($camp);
                    $l_camp->resume();

                    // Log
                    $l_camp->log('restarted', $customer);
                }
            }
        }

        // Redirect to my lists page
        echo trans('messages.campaigns.restarted');
    }

    /**
     * Subscribers list.
     */
    public function subscribers(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        $subscribers = $campaign->subscribers();

        return view('campaigns.subscribers', [
            'subscribers' => $subscribers,
            'campaign' => $campaign,
            'list' => $campaign->defaultMailList,
        ]);
    }

    /**
     * Subscribers listing.
     */
    public function subscribersListing(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return;
        }

        // Subscribers
        $subscribers = $campaign->getDeliveryReport()
            ->addSelect('subscribers.*')
            ->addSelect('bounce_logs.raw AS bounced_message')
            ->addSelect('feedback_logs.feedback_type AS feedback_message')
            ->addSelect('tracking_logs.error AS failed_message');

        // Check open conditions
        if ($request->open) {
            // Query of email addresses that DID open
            $openByEmails = $campaign->openLogs()->join('subscribers', 'tracking_logs.subscriber_id', '=', 'subscribers.id')->groupBy('subscribers.email')->select('subscribers.email');

            if ($request->open == 'yes') {
                $subscribers = $subscribers->joinSub($openByEmails, 'OpenedByEmails', function ($join) {
                    $join->on('subscribers.email', '=', 'OpenedByEmails.email');
                });
            } elseif ($request->open = 'no') {
                $subscribers = $subscribers->leftJoinSub($openByEmails, 'OpenedByEmails', function ($join) {
                    $join->on('subscribers.email', '=', 'OpenedByEmails.email');
                })->whereNull('OpenedByEmails.email');
            }
        }

        // Check click conditions
        if ($request->click) {
            // Query of email addresses that DID click
            $clickByEmails = $campaign->clickLogs()->join('subscribers', 'tracking_logs.subscriber_id', '=', 'subscribers.id')->groupBy('subscribers.email')->select('subscribers.email');

            if ($request->click == 'clicked') {
                $subscribers = $subscribers->joinSub($clickByEmails, 'ClickedByEmails', function ($join) {
                    $join->on('subscribers.email', '=', 'ClickedByEmails.email');
                });
            } elseif ($request->click = 'not_clicked') {
                $subscribers = $subscribers->leftJoinSub($clickByEmails, 'ClickedByEmails', function ($join) {
                    $join->on('subscribers.email', '=', 'ClickedByEmails.email');
                })->whereNull('ClickedByEmails.email');
            }
        }

        // Paging
        $subscribers = $subscribers->search($request->keyword)->paginate($request->per_page ? $request->per_page : 50);

        // Field information
        $fields = $campaign->defaultMailList->getFields->whereIn('uid', $request->columns);

        return view('campaigns._subscribers_list', [
            'subscribers' => $subscribers,
            'list' => $campaign->defaultMailList,
            'campaign' => $campaign,
            'fields' => $fields,
        ]);
    }

    /**
     * Buiding email template.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function templateBuild(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        $elements = [];
        if (isset($request->style)) {
            $elements = \Acelle\Model\Template::templateStyles()[$request->style];
        }

        return view('campaigns.template_build', [
            'campaign' => $campaign,
            'elements' => $elements,
            'list' => $campaign->defaultMailList,
        ]);
    }

    /**
     * Re-Buiding email template.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function templateRebuild(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.template_rebuild', [
            'campaign' => $campaign,
            'list' => $campaign->defaultMailList,
        ]);
    }

    /**
     * Copy campaign.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function copy(Request $request)
    {
        $campaign = Campaign::findByUid($request->copy_campaign_uid);

        // authorize
        if (\Gate::denies('copy', $campaign)) {
            return $this->notAuthorized();
        }

        if ($request->isMethod('post')) {
            // make validator
            $validator = \Validator::make($request->all(), [
                'name' => 'required',
            ]);

            // redirect if fails
            if ($validator->fails()) {
                return response()->view('campaigns.copy', [
                    'campaign' => $campaign,
                    'errors' => $validator->errors(),
                ], 400);
            }

            $campaign->copy($request->name);
            return trans('messages.campaign.copied');
        }

        return view('campaigns.copy', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Send email for testing campaign.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function sendTestEmail(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        if ($request->isMethod('post')) {
            $validator = \Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            //
            if ($validator->fails()) {
                return response()->view('campaigns.sendTestEmail', [
                    'campaign' => $campaign,
                    'errors' => $validator->errors(),
                ], 400);
            }

            $sending = $campaign->sendTestEmail($request->email);

            return response()->json($sending);
        }

        return view('campaigns.sendTestEmail', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Preview template.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function preview($id)
    {
        $campaign = Campaign::findByUid($id);

        // authorize
        if (\Gate::denies('preview', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.preview', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Preview content template.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function previewContent(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);
        $subscriber = Subscriber::findByUid($request->subscriber_uid);

        // authorize
        if (\Gate::denies('preview', $campaign)) {
            return $this->notAuthorized();
        }

        echo $campaign->getHtmlContent($subscriber);
    }

    /**
     * List segment form.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function listSegmentForm(Request $request)
    {
        // Get current user
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns._list_segment_form', [
            'campaign' => $campaign,
            'lists_segment_group' => [
                'list' => null,
                'is_default' => false,
            ],
        ]);
    }

    /**
     * Change template from exist template.
     *
     */
    public function templateChangeTemplate(Request $request, $uid, $template_uid)
    {
        // Generate info
        $campaign = Campaign::findByUid($uid);
        $changeTemplate = Template::findByUid($template_uid);

        // authorize
        if (!$request->user()->customer->can('update', $campaign)) {
            return $this->notAuthorized();
        }

        $campaign->changeTemplate($changeTemplate);
    }

    /**
     * Email web view.
     */
    public function webView(Request $request)
    {
        $message_id = StringHelper::base64UrlDecode($request->message_id);
        $tracking_log = TrackingLog::where('message_id', '=', $message_id)->first();

        try {
            if (!$tracking_log) {
                throw new \Exception(trans('messages.web_view_can_not_find_tracking_log_with_message_id'));
            }

            $subscriber = $tracking_log->subscriber;
            $campaign = $tracking_log->campaign;

            if (!$campaign || !$subscriber) {
                throw new \Exception(trans('messages.web_view_can_not_find_campaign_or_subscriber'));
            }

            return view('campaigns.web_view', [
                'campaign' => $campaign,
                'subscriber' => $subscriber,
                'message_id' => $message_id,
            ]);
        } catch (\Exception $e) {
            return view('somethingWentWrong', ['message' => trans('messages.the_email_no_longer_exists')]);
        }
    }

    /**
     * Email web view for previewing before sending
     */
    public function webViewPreview(Request $request)
    {
        $subscriber = Subscriber::findByUid($request->subscriber_uid);
        $campaign = Campaign::findByUid($request->campaign_uid);

        if (is_null($subscriber) || is_null($campaign)) {
            throw new \Exception('Invalid subscriber or campaign UID');
        }

        return view('campaigns.web_view', [
            'campaign' => $campaign,
            'subscriber' => $subscriber,
            'message_id' => null,
        ]);
    }

    /*
     * Select campaign type page.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function selectType(Request $request)
    {
        // authorize
        if (\Gate::denies('create', new Campaign())) {
            return $this->notAuthorized();
        }

        return view('campaigns.select_type');
    }

    /**
     * Template review.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function templateReview(Request $request)
    {
        // Get current user
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.template_review', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Template review iframe.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function templateReviewIframe(Request $request)
    {
        // Get current user
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.template_review_iframe', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Resend the specified campaign.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function resend(Request $request, $uid)
    {
        $customer = $request->user()->customer;
        $campaign = Campaign::findByUid($uid);

        // do resend with option: $request->option : not_receive|not_open|not_click
        if ($request->isMethod('post')) {
            // authorize
            if (\Gate::allows('resend', $campaign)) {
                $linked_campaigns = json_decode($campaign->linked_campaigns);
                foreach ($linked_campaigns as $camp) {
                    $l_camp = Campaign::find($camp);
                    $l_camp->resend($request->option);
                }
                // Redirect to my lists page
                return response()->json([
                    'status' => 'success',
                    'message' => trans('messages.campaign.resent'),
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => trans('messages.not_authorized_message'),
                ], 400);
            }
        }

        return view('campaigns.resend', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Get spam score.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string
     */
    public function spamScore(Request $request, $uid)
    {
        // Get current user
        $campaign = Campaign::findByUid($uid);

        try {
            $score = $campaign->score();
        } catch (\Exception $e) {
            return response()->json("Cannot get score. Make sure you setup for SpamAssassin correctly.\r\n" . $e->getMessage(), 500); // Status code here
        }

        return view('campaigns.spam_score', [
            'score' => $score,
        ]);
    }

    /**
     * Edit email content.
     *
     */
    public function builderClassic(Request $request, $uid)
    {
        // Generate info
        $campaign = Campaign::findByUid($uid);

        // authorize
        if (!$request->user()->customer->can('update', $campaign)) {
            return $this->notAuthorized();
        }

        // validate and save posted data
        if ($request->isMethod('post')) {
            $rules = array(
                'html' => 'required',
            );

            // make validator
            $validator = \Validator::make($request->all(), $rules);

            // redirect if fails
            if ($validator->fails()) {
                // faled
                return response()->json($validator->errors(), 400);
            }

            // UGLY CODE here, @todo: find a better place to house this type of validation
            $plan = $request->user()->customer->getCurrentActiveSubscription()->plan;
            if ($plan->getOption('unsubscribe_url_required') == 'yes' && Setting::isYes('campaign.enforce_unsubscribe_url_check')) {
                if (strpos($request->html, '{UNSUBSCRIBE_URL}') === false) {
                    return response()->json(['message' => trans('messages.template.validation.unsubscribe_url_required')], 400);
                }
            }

            // Save template
            $campaign->setTemplateContent($request->html);
            $campaign->preheader = $request->preheader;
            $campaign->save();

            // update plain
            $campaign->updatePlainFromHtml();

            // success
            return response()->json([
                'status' => 'success',
                'message' => trans('messages.template.updated'),
            ], 201);
        }

        return view('campaigns.builderClassic', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Edit plain text.
     *
     */
    public function builderPlainEdit(Request $request, $uid)
    {
        // Generate info
        $campaign = Campaign::findByUid($uid);

        // authorize
        if (!$request->user()->customer->can('update', $campaign)) {
            return $this->notAuthorized();
        }

        // validate and save posted data
        if ($request->isMethod('post')) {
            $rules = array(
                'plain' => 'required',
            );

            // make validator
            $validator = \Validator::make($request->all(), $rules);

            // redirect if fails
            if ($validator->fails()) {
                // faled
                return response()->json($validator->errors(), 400);
            }

            // Save template
            $campaign->plain = $request->plain;
            $campaign->save();

            // success
            return response()->json([
                'status' => 'success',
                'message' => trans('messages.template.updated'),
            ], 201);
        }

        return view('campaigns.builderPlainEdit', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Upload attachment.
     *
     */
    public function uploadAttachment(Request $request, $uid)
    {
        // Generate info
        $campaign = Campaign::findByUid($uid);

        // authorize
        if (!$request->user()->customer->can('update', $campaign)) {
            return $this->notAuthorized();
        }

        foreach ($request->file as $file) {
            $campaign->uploadAttachment($file);
        }
    }

    /**
     * Download attachment.
     *
     */
    public function downloadAttachment(Request $request, $uid)
    {
        // Generate info
        $campaign = Campaign::findByUid($uid);

        // authorize
        if (!$request->user()->customer->can('update', $campaign)) {
            return $this->notAuthorized();
        }

        return response()->download($campaign->getAttachmentPath($request->name), $request->name);
    }

    /**
     * Remove attachment.
     *
     */
    public function removeAttachment(Request $request, $uid)
    {
        // Generate info
        $campaign = Campaign::findByUid($uid);

        // authorize
        if (!$request->user()->customer->can('update', $campaign)) {
            return $this->notAuthorized();
        }

        unlink($campaign->getAttachmentPath($request->name));
    }

    public function updateStats(Request $request, $uid)
    {
        $campaign = Campaign::findByUid($uid);

        // authorize
        if (!$request->user()->customer->can('update', $campaign)) {
            return $this->notAuthorized();
        }

        $campaign->updateCache();
        echo $campaign->status;
    }

    public function notification(Request $request)
    {
        $message = StringHelper::base64UrlDecode($request->message);
        return response($message, 200)->header('Content-Type', 'text/plain');
    }

    public function customPlainOn(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (!$request->user()->customer->can('update', $campaign)) {
            return $this->notAuthorized();
        }

        $campaign->plain = 'something';
        $campaign->save();

        return redirect()->action('CampaignController@builderPlainEdit', [
            'uid' => $campaign->uid,
        ]);
    }

    public function customPlainOff(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (!$request->user()->customer->can('update', $campaign)) {
            return $this->notAuthorized();
        }

        $campaign->plain = null;
        $campaign->save();

        return redirect()->action('CampaignController@builderPlainEdit', [
            'uid' => $campaign->uid,
        ]);
    }

    public function previewAs(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        return view('campaigns.previewAs', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Subscribers listing.
     */
    public function previewAsList(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return;
        }

        // Subscribers
        $subscribers = $campaign->subscribers()
            ->search($request->keyword)->paginate($request->per_page);

        return view('campaigns.previewAsList', [
            'subscribers' => $subscribers,
            'campaign' => $campaign,
        ]);
    }

    public function webhooks(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.webhooks', [
            'campaign' => $campaign,
        ]);
    }

    public function webhooksList(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.webhooksList', [
            'campaign' => $campaign,
        ]);
    }

    public function webhooksAdd(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);
        $webhook = $campaign->newWebhook();

        // authorize
        if (\Gate::denies('update', $campaign)) {
            return $this->notAuthorized();
        }

        if ($request->isMethod('post')) {
            list($webhook, $validator) = $webhook->createFromArray($request->all());

            // redirect if fails
            if ($validator->fails()) {
                return response()->view('campaigns.webhooksAdd', [
                    'campaign' => $campaign,
                    'webhook' => $webhook,
                    'errors' => $validator->errors(),
                ], 400);
            }

            return response()->json([
                'message' => trans('messages.webhook.added'),
            ]);
        }

        return view('campaigns.webhooksAdd', [
            'campaign' => $campaign,
            'webhook' => $webhook,
        ]);
    }

    public function webhooksLinkSelect(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.webhooksLinkSelect', [
            'campaign' => $campaign,
        ]);
    }

    public function webhooksEdit(Request $request)
    {
        $webhook = \Acelle\Model\CampaignWebhook::findByUid($request->webhook_uid);

        // authorize
        if (\Gate::denies('update', $webhook->campaign)) {
            return $this->notAuthorized();
        }

        if ($request->isMethod('post')) {
            list($webhook, $validator) = $webhook->updateFromArray($request->all());

            // redirect if fails
            if ($validator->fails()) {
                return response()->view('campaigns.webhooksEdit', [
                    'webhook' => $webhook,
                    'errors' => $validator->errors(),
                ], 400);
            }

            return response()->json([
                'message' => trans('messages.webhook.updated'),
            ]);
        }

        return view('campaigns.webhooksEdit', [
            'webhook' => $webhook,
        ]);
    }

    public function webhooksDelete(Request $request)
    {
        $webhook = \Acelle\Model\CampaignWebhook::findByUid($request->webhook_uid);

        // authorize
        if (\Gate::denies('update', $webhook->campaign)) {
            return $this->notAuthorized();
        }

        $webhook->delete();

        return response()->json([
            'message' => trans('messages.webhook.deleted'),
        ]);
    }

    public function webhooksSampleRequest(Request $request)
    {
        $webhook = \Acelle\Model\CampaignWebhook::findByUid($request->webhook_uid);

        // authorize
        if (\Gate::denies('read', $webhook->campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.webhooksSampleRequest', [
            'webhook' => $webhook,
        ]);
    }

    public function webhooksTest(Request $request)
    {
        $webhook = \Acelle\Model\CampaignWebhook::findByUid($request->webhook_uid);
        $result = null;

        // authorize
        if (\Gate::denies('read', $webhook->campaign)) {
            return $this->notAuthorized();
        }

        if ($request->isMethod('post')) {
            $client = new \GuzzleHttp\Client();

            try {
                $response = $client->request('GET', $webhook->endpoint, [
                    'headers' => [
                        "content-type" => "application/json"
                    ],
                    'body' => '{hello: "world"}',
                    'http_errors' => false,
                ]);

                $result = [
                    'status' => 'sent',
                    'code' => $response->getStatusCode(),
                    'message' => $response->getReasonPhrase(),
                ];
            } catch (\Exception $e) {
                $result = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return view('campaigns.webhooksTest', [
            'webhook' => $webhook,
            'result' => $result,
        ]);
    }

    public function webhooksTestMessage(Request $request, $webhook_uid, $message_id)
    {
        $webhook = \Acelle\Model\CampaignWebhook::findByUid($request->webhook_uid);
        $result = null;

        // authorize
        if (\Gate::denies('read', $webhook->campaign)) {
            return $this->notAuthorized();
        }

        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->request('GET', $webhook->endpoint, [
                'headers' => [
                    "content-type" => "application/json"
                ],
                'body' => '{hello: "world"}',
                'http_errors' => false,
            ]);

            $result = [
                'status' => 'sent',
                'code' => $response->getStatusCode(),
                'message' => $response->getReasonPhrase(),
                'message_id' => $message_id,
                'endpoint' => $webhook->endpoint,
                'responseBody' => $response->getBody(),
            ];
        } catch (\Exception $e) {
            $result = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'message_id' => $message_id,
                'endpoint' => $webhook->endpoint,
                'responseBody' => $response->getBody(),
            ];
        }

        return view('campaigns.webhooksTestMessage', [
            'webhook' => $webhook,
            'result' => $result,
        ]);
    }

    /**
     * Click logs execute.
     */
    public function clickLogExecute(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.clickLogExecute', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Open logs execute.
     */
    public function openLogExecute(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        // authorize
        if (\Gate::denies('read', $campaign)) {
            return $this->notAuthorized();
        }

        return view('campaigns.openLogExecute', [
            'campaign' => $campaign,
        ]);
    }

    protected function markBounced($data)
    {
        $msgId = $data['payload']['message']['message_id'];

        // // check tracking log if message exits
        // $tracking_log = TrackingLog::where('message_id', $msgId)->count();
        // if (!$tracking_log) {
        //     return response()->json(['status' => 0, 'email' => $data['payload']['message']['to'], 'message' => 'Message not found']);
        // }

        $bounceLog = new BounceLog();
        $bounceLog->message_id = $msgId;
        $bounceLog->runtime_message_id = $msgId;
        $bounceLog->bounce_type = 'hard'; // soft | hard | unknown
        $bounceLog->status_code = 555; // 511, 550, 555... (hard) or 4xx (soft)
        $bounceLog->raw = json_encode($data);
        $bounceLog->save();

        $Blacklist = new Blacklist();
        $Blacklist->email = $data['payload']['message']['to'];
        $Blacklist->reason = isset($data['payload']['details']) ? $data['payload']['details'] : 'unknown';
        $Blacklist->admin_id = 1;
        $Blacklist->customer_id = 4;
        $Blacklist->save();

        $subscriber = Subscriber::where('email', $data['payload']['message']['to'])->first();
        if (isset($subscriber->id)) {
            $subscriber->status = 'blacklisted';
            $subscriber->save();
        }

        $this->update_main_bounces($data['payload']['message']['to']);

        return response()->json(['status' => 1, 'email' => $data['payload']['message']['to'], 'message' => 'Email marked as bounced']);
    }

    public function imap_webhook(Request $request)
    {

        $data = $request->all();
        Log::channel('imap_webhook')->info(json_encode($data));
        if (isset($data['event']) && $data['event'] == 'MessageBounced') {
            return  $this->markBounced($data);
        } elseif (isset($data['event']) && $data['event'] == 'MessageDeliveryFailed' && $data['payload']['status'] == 'HardFail') {
            return $this->markBounced($data);
        } elseif (isset($data['event']) && $data['event'] == 'messageBounce') {
            if (isset($data['data']['messageId']) && $data['data']['messageId']) {
                $msgId = str_replace('>', '', str_replace('<', '', $data['data']['messageId']));

                $bounceLog = new BounceLog();
                $bounceLog->message_id = $msgId;
                $bounceLog->runtime_message_id = $msgId;
                $bounceLog->bounce_type = 'hard'; // soft | hard | unknown
                $bounceLog->status_code = 555; // 511, 550, 555... (hard) or 4xx (soft)
                $bounceLog->raw = json_encode($data['data']);
                $bounceLog->save();

                $Blacklist = new Blacklist();
                $Blacklist->email = $data['data']['recipient'];
                $Blacklist->reason = isset($data['data']['response']['message']) ? $data['data']['response']['message'] : 'unknown';
                $Blacklist->admin_id = 1;
                $Blacklist->customer_id = 4;
                $Blacklist->save();

                $subscriber = Subscriber::where('email', $data['data']['recipient'])->first();
                if (isset($subscriber->id)) {
                    $subscriber->status = 'blacklisted';
                    $subscriber->save();
                }
                $this->update_main_bounces($data['data']['recipient']);
            }
        } elseif (isset($data['event']) && $data['event'] == 'messageNew' && ($data['path'] == 'INBOX' || $data['path'] == '[Gmail]/All Mail')) {
            $account = $data['account'];
            $id = $data['data']['id'];

            // check if sending server is enabled
            $sending_server = \Acelle\Model\SendingServer::where('options', 'like', "%$account%")->where('status', 'active')->first();
            if (isset($sending_server->id)) {

                $message_data = Http::withHeaders([
                    'Authorization' => 'Bearer ' . env('EE_AUTH'),
                    'content-type' => 'application/json'
                ])->get(env('EE_BASE') . "/account/$account/message/$id");

                $message = $message_data->json();
                // check if its a reply 
                if (isset($message['inReplyTo']) && $message['inReplyTo']) {
                    $message_id = str_replace('>', '', str_replace('<', '', $message['inReplyTo']));
                    $this->markUnsub($message_id);
                }

                if (!str_contains($data['data']['subject'], 'V3GP0AF')) {



                    // Log::info($message);

                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . env('EE_AUTH'),
                        'content-type' => 'application/json'
                    ])->get(env('EE_BASE') . "/account/$account/message/$id/source");

                    // Log::channel('imap_webhook')->info($response);

                    // $mailbox2 = array(
                    //     'mailbox'  => '{imap.gmail.com:993/imap/ssl}INBOX',
                    //     'username' => 'sukhwindersodhi62@gmail.com',
                    //     'password' => 'wbktlmdxvozdedjb'
                    // );

                    $mailbox2 = array(
                        'mailbox'  => '{mail.gandi.net:993/imap/ssl}INBOX',
                        'username' => 'noreply@emailpanther.digital',
                        'password' => 'import3891'
                    );

                    $stream2 = imap_open($mailbox2['mailbox'], $mailbox2['username'], $mailbox2['password'])
                        or Log::info('Cannot connect to mailbox: ' . imap_last_error());
                    $check = imap_check($stream2);
                    Log::channel('imap_webhook')->info("Msg Count before append: " . $check->Nmsgs . "\n");

                    imap_append($stream2, '{mail.gandi.net:993/imap/ssl}INBOX', $response);
                    $check = imap_check($stream2);
                    Log::channel('imap_webhook')->info("Msg Count after append : " . $check->Nmsgs . "\n");
                } else {
                    Log::channel('imap_webhook')->info("Not mapped:- " . $data['data']['subject']);
                }
            }
        }
    }

    public function get_mails()
    {
        $mailbox2 = array(
            'mailbox'  => '{mail.gandi.net:993/imap/ssl}INBOX',
            'username' => 'noreply@emailpanther.digital',
            'password' => 'import3891'
        );

        $conn = imap_open($mailbox2['mailbox'], $mailbox2['username'], $mailbox2['password'])
            or Log::info('Cannot connect to mailbox: ' . imap_last_error());

        /* Search emails from inbox*/
        $mails = imap_search($conn, 'SUBJECT "V3GP0AF"');

        rsort($mails);
        echo "<pre>";
        print_r($mails);

        foreach ($mails as $messageId) {
            imap_delete($conn, $messageId);
        }

        imap_expunge($conn);

        imap_close($conn);
    }

    protected function markUnsub($message_id)
    {
        $tracking_log = TrackingLog::where('message_id', $message_id)->first();
        if (isset($tracking_log->id)) {
            $campaign = Campaign::find($tracking_log->campaign_id);
            if (isset($campaign->id)) {

                $subscriber = Subscriber::find($tracking_log->subscriber_id);

                $unsubscriber = new Unsubscriber();
                $unsubscriber->message_id = $message_id;
                $unsubscriber->email = $subscriber->email;
                $unsubscriber->subscriber_id = $subscriber->id;
                $unsubscriber->campaign_id = $campaign->id;
                $unsubscriber->ip_address = isset($trackingInfo['ip_address']) ? $trackingInfo['ip_address'] : '';
                $unsubscriber->save();

                $UnsubscribeLog = new UnsubscribeLog();
                $UnsubscribeLog->message_id = $message_id;
                $UnsubscribeLog->ip_address = isset($trackingInfo['ip_address']) ? $trackingInfo['ip_address'] : '';
                $UnsubscribeLog->subscriber_id = $subscriber->id;
                $UnsubscribeLog->user_agent = isset($trackingInfo['user_agent']) ? $trackingInfo['user_agent'] : '';
                $UnsubscribeLog->save();
            }
        }
    }

    protected function forward_message($mail_data, $ee_account)
    {
        $mail_data = [
            'from' => [
                'name' => (isset($mail_data['from']['name']) && $mail_data['from']['name']) ? $mail_data['from']['name'] : $mail_data['from']['address'],
                'address' => $mail_data['from']['address']
            ],

            'to' => [
                [
                    'name' => $mail_data['to'][0]['name'],
                    'address' => $mail_data['to'][0]['address']
                ],
            ],
            'subject' => $mail_data['subject'],
            "html" => $mail_data['text']['html'],
            // "attachments" => $attachments,
        ];

        // Log::info(json_encode($mail_data));
        Log::info(env('EE_BASE') . "/account/$ee_account/submit");
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('EE_AUTH'),
            'content-type' => 'application/json'
        ])->post(env('EE_BASE') . "/account/$ee_account/submit", $mail_data);

        // Log::info(json_encode($response->json()));
        $mail_response = $response->json();
        if (!isset($mail_response['statusCode'])) {
            // Log::info('Sent!');
        } else if (isset($mail_response['statusCode'])) {
            throw new \Exception($mail_response['message']);
        }
    }

    public function add_step(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        if ($campaign->linked_campaigns) {
            $linked_campaigns = json_decode($campaign->linked_campaigns);
            if (count($linked_campaigns)) {
                foreach ($linked_campaigns as $camp) {
                    if ($camp == $campaign->id) {
                        $linked_campaign = Campaign::find($camp);
                        list($CampaignSteps, $key) = $this->new_step($linked_campaign, $request);
                    } else {
                        $linked_campaign = Campaign::find($camp);
                        $this->new_step($linked_campaign, $request);
                    }
                }
            }
        } else {
            list($CampaignSteps, $key) = $this->new_step($campaign, $request);
        }

        $step = view('campaigns.template.steps', ['campaign' => $campaign, 'step' => $CampaignSteps, 'key' => $key])->render();

        return response()->json(['html' => $step]);
    }

    protected function new_step($campaign, $request)
    {

        $CampaignSteps = new CampaignSteps;
        $CampaignSteps->campaign_id = $campaign->id;
        $CampaignSteps->next_step_wait_time = 7;
        $CampaignSteps->step_number = $request->step_number;
        $CampaignSteps->save();

        $CampaignStepsVariant = new CampaignStepsVariant;
        $CampaignStepsVariant->campaign_steps_id = $CampaignSteps->id;
        $CampaignStepsVariant->variant = 1;
        $CampaignStepsVariant->subject = '';
        $CampaignStepsVariant->content = '';
        $CampaignStepsVariant->save();

        $key = count($campaign->steps) - 1;

        $campaign->fresh();

        return [$CampaignSteps, $key];
    }

    public function delete_step(Request $request)
    {

        $campaign = Campaign::findByUid($request->uid);
        $CampaignSteps = CampaignSteps::find($request->id);
        if ($campaign->linked_campaigns) {
            $linked_campaigns = json_decode($campaign->linked_campaigns);
            if (count($linked_campaigns)) {
                foreach ($linked_campaigns as $camp) {
                    $campaign_data = Campaign::find($camp);
                    if ($camp != $campaign->id) {
                        $camp_step = CampaignSteps::where(['campaign_id' => $camp, 'step_number' => $CampaignSteps->step_number])->first();
                        if (isset($camp_step->id)) {
                            $CampaignStepsSettings = CampaignStepsSettings::where('campaign_step_id', $camp_step->id)->delete();
                            $CampaignStepsVariant = CampaignStepsVariant::where('campaign_steps_id', $camp_step->id)->delete();
                            $camp_step->delete();
                        }
                    } else {
                        $CampaignStepsSettings = CampaignStepsSettings::where('campaign_step_id', $request->id)->delete();
                        $CampaignStepsVariant = CampaignStepsVariant::where('campaign_steps_id', $request->id)->delete();
                        $CampaignSteps->delete();
                        $campaign->fresh();
                    }
                    $steps = CampaignSteps::where('campaign_id', $campaign_data->id)->orderby('step_number', 'asc')->get();
                    if (count($steps)) {
                        foreach ($steps as $key => $step) {
                            $step_number = $key + 1;
                            CampaignSteps::where('campaign_id', $campaign_data->id)
                                ->where('step_number', $step->step_number)
                                ->update(['step_number' => $step_number]);
                        }
                    }
                }
            }
        } else {
            $CampaignStepsSettings = CampaignStepsSettings::where('campaign_step_id', $request->id)->delete();
            $CampaignStepsVariant = CampaignStepsVariant::where('campaign_steps_id', $request->id)->delete();
            $CampaignSteps->delete();
            $campaign->fresh();
            $steps = CampaignSteps::find($request->id);
            if (count($steps)) {
                foreach ($steps as $key => $step) {
                    $step_number = $key + 1;
                    CampaignSteps::where('campaign_id', $step->campaign_id)
                        ->where('step_number', $step->step_number)
                        ->update(['step_number' => $step_number]);
                }
            }
        }

        return response()->json(['status' => 1]);
    }

    public function update_subject(Request $request)
    {
        $CampaignStepsVariant = CampaignStepsVariant::find($request->variant);

        if (isset($CampaignStepsVariant->id)) {
            $camp_step = CampaignSteps::find($CampaignStepsVariant->campaign_steps_id);
            if (isset($camp_step->id)) {
                $campaign = Campaign::find($camp_step->campaign_id);
                if (isset($campaign->id)) {
                    $linked_campaigns = json_decode($campaign->linked_campaigns);
                    if (count($linked_campaigns)) {
                        foreach ($linked_campaigns as $camp) {
                            $link_step = CampaignSteps::where([
                                'campaign_id' => $camp,
                                'step_number' => $camp_step->step_number
                            ])->first();
                            if (isset($link_step->id)) {
                                $link_variant = CampaignStepsVariant::where('campaign_steps_id', $link_step->id)->where('variant', $CampaignStepsVariant->variant)->first();
                                $link_variant->subject = $request->subject;
                                $link_variant->save();
                            } else {
                                $link_step = new CampaignSteps();
                                $link_step->campaign_id = $camp;
                                $link_step->step_number = $camp_step->step_number;
                                $link_step->next_step_wait_time = $camp_step->next_step_wait_time;
                                $link_step->save();

                                // main variant
                                $main_variant = CampaignStepsVariant::where('campaign_steps_id', $camp_step->id)->where('variant', $CampaignStepsVariant->variant)->first();

                                $link_variant = new CampaignStepsVariant;

                                $link_variant->subject = $request->subject;
                                $link_variant->content = $main_variant->content;
                                $link_variant->campaign_steps_id = $link_step->id;
                                $link_variant->variant = $main_variant->variant;
                                $link_variant->status = $main_variant->status;
                                $link_variant->save();
                            }
                        }
                    }
                }
            }
        }

        $CampaignStepsVariant->subject = $request->subject;
        $CampaignStepsVariant->save();
        return response()->json(['status' => 1]);
    }

    public function update_content(Request $request)
    {
        $CampaignStepsVariant = CampaignStepsVariant::find($request->variant);

        if (isset($CampaignStepsVariant->id)) {
            $camp_step = CampaignSteps::find($CampaignStepsVariant->campaign_steps_id);
            if (isset($camp_step->id)) {
                $campaign = Campaign::find($camp_step->campaign_id);
                if (isset($campaign->id)) {
                    $linked_campaigns = json_decode($campaign->linked_campaigns);
                    if (count($linked_campaigns)) {
                        foreach ($linked_campaigns as $camp) {
                            $link_step = CampaignSteps::where([
                                'campaign_id' => $camp,
                                'step_number' => $camp_step->step_number
                            ])->first();
                            if (isset($link_step->id)) {
                                $link_variant = CampaignStepsVariant::where('campaign_steps_id', $link_step->id)->where('variant', $CampaignStepsVariant->variant)->first();
                                $link_variant->content = $request->content;
                                $link_variant->save();
                            } else {
                                $link_step = new CampaignSteps();
                                $link_step->campaign_id = $camp;
                                $link_step->step_number = $camp_step->step_number;
                                $link_step->next_step_wait_time = $camp_step->next_step_wait_time;
                                $link_step->save();

                                // main variant
                                $main_variant = CampaignStepsVariant::where('campaign_steps_id', $camp_step->id)->where('variant', $CampaignStepsVariant->variant)->first();

                                $link_variant = new CampaignStepsVariant;

                                $link_variant->subject = $request->subject;
                                $link_variant->content = $main_variant->content;
                                $link_variant->campaign_steps_id = $link_step->id;
                                $link_variant->variant = $main_variant->variant;
                                $link_variant->status = $main_variant->status;
                                $link_variant->save();
                            }
                        }
                    }
                }
            }
        }
        $CampaignStepsVariant->content = $request->content;
        $CampaignStepsVariant->save();
        return response()->json(['status' => 1]);
    }

    public function wait_for(Request $request)
    {
        $CampaignSteps = CampaignSteps::find($request->step_number);
        if (isset($CampaignSteps->id)) {
            $campaign = Campaign::find($CampaignSteps->campaign_id);
            if (isset($campaign->id)) {
                $linked_campaigns = json_decode($campaign->linked_campaigns);
                if (count($linked_campaigns)) {
                    foreach ($linked_campaigns as $camp) {
                        $link_step = CampaignSteps::where([
                            'campaign_id' => $camp,
                            'step_number' => $CampaignSteps->step_number
                        ])->first();

                        $link_step->next_step_wait_time = $request->next_step_wait_time;
                        $link_step->save();
                    }
                }
            }
        }

        $CampaignSteps->next_step_wait_time = $request->next_step_wait_time;
        $CampaignSteps->save();
        return response()->json(['status' => 1]);
    }

    public function add_variant(Request $request)
    {
        $last_variant = CampaignStepsVariant::where('campaign_steps_id', $request->step)->orderby('id', 'desc')->first();

        $CampaignSteps = CampaignSteps::find($request->step);
        if (isset($CampaignSteps->id)) {
            $campaign = Campaign::find($CampaignSteps->campaign_id);
            if (isset($campaign->id)) {
                $linked_campaigns = json_decode($campaign->linked_campaigns);
                if (count($linked_campaigns)) {
                    foreach ($linked_campaigns as $camp) {
                        $link_step = CampaignSteps::where([
                            'campaign_id' => $camp,
                            'step_number' => $CampaignSteps->step_number
                        ])->first();


                        $last_variant = CampaignStepsVariant::where('campaign_steps_id', $link_step->id)->orderby('id', 'desc')->first();

                        $CampaignStepsVariant = new CampaignStepsVariant;
                        $CampaignStepsVariant->campaign_steps_id = $link_step->id;
                        $CampaignStepsVariant->variant = $last_variant->variant + 1;
                        $CampaignStepsVariant->subject = '';
                        $CampaignStepsVariant->content = '';
                        $CampaignStepsVariant->save();
                    }
                }
            }
        }

        $all_variants = CampaignStepsVariant::where('campaign_steps_id', $request->step)->orderby('id', 'asc')->get();
        $step = CampaignSteps::find($request->step);

        $variants = view('campaigns.template.variants', compact('step'))->render();

        return response()->json(['variants' => $variants]);
    }

    public function delete_variant(Request $request)
    {
        $variant = CampaignStepsVariant::find($request->variant);

        $CampaignSteps = CampaignSteps::find($variant->campaign_steps_id);
        if (isset($CampaignSteps->id)) {
            $campaign = Campaign::find($CampaignSteps->campaign_id);
            if (isset($campaign->id)) {
                $linked_campaigns = json_decode($campaign->linked_campaigns);
                if (count($linked_campaigns)) {
                    foreach ($linked_campaigns as $camp) {
                        $link_step = CampaignSteps::where([
                            'campaign_id' => $camp,
                            'step_number' => $CampaignSteps->step_number
                        ])->first();


                        CampaignStepsVariant::where('campaign_steps_id', $link_step->id)->where('variant', $variant->variant)->delete();

                        $records = CampaignStepsVariant::where('campaign_steps_id', $link_step->id)->orderby('id', 'asc')->get();

                        if (count($records)) {
                            foreach ($records as $key => $row) {
                                $row->variant = ++$key;
                                $row->save();
                            }
                        }
                    }
                }
            }
        }


        $all_variants = CampaignStepsVariant::where('campaign_steps_id', $request->step)->orderby('id', 'asc')->get();
        $step = CampaignSteps::find($request->step);

        $variants = view('campaigns.template.variants', compact('step'))->render();

        return response()->json(['variants' => $variants]);
    }

    public function update_variant_status(Request $request)
    {
        $variant = CampaignStepsVariant::where('id', $request->variant)->first();
        $CampaignSteps = CampaignSteps::find($variant->campaign_steps_id);
        if (isset($CampaignSteps->id)) {
            $campaign = Campaign::find($CampaignSteps->campaign_id);
            if (isset($campaign->id)) {
                $linked_campaigns = json_decode($campaign->linked_campaigns);
                if (count($linked_campaigns)) {
                    foreach ($linked_campaigns as $camp) {
                        $link_step = CampaignSteps::where([
                            'campaign_id' => $camp,
                            'step_number' => $CampaignSteps->step_number
                        ])->first();

                        $variant_s = CampaignStepsVariant::where('campaign_steps_id', $link_step->id)->first();
                        $variant_s->status = $request->status;
                        $variant_s->save();
                    }
                }
            }
        }


        return response()->json(['variant' => $variant]);
    }

    public function save_settings(Request $request)
    {
        $step = CampaignSteps::find($request->step_id);
        if (isset($step->id)) {
            $campaign = Campaign::find($step->campaign_id);
            if (isset($campaign->id)) {
                $linked_campaigns = json_decode($campaign->linked_campaigns);
                if (count($linked_campaigns)) {
                    foreach ($linked_campaigns as $camp) {
                        $link_step = CampaignSteps::where([
                            'campaign_id' => $camp,
                            'step_number' => $step->step_number
                        ])->first();

                        $step->next_step_wait_time = $request->next_step_wait_time;

                        CampaignStepsSettings::where('campaign_step_id', $link_step->step_id)->delete();

                        if (count($request->condition)) {
                            foreach ($request->condition as $key => $condition) {
                                $settings = new CampaignStepsSettings();
                                $settings->campaign_step_id = $link_step->id;
                                $settings->condition = $condition;
                                $settings->condition_purpose = $request->condition_value[$key];
                                $settings->wait_time = $request->wait_time[$key];
                                $settings->save();
                            }
                        }
                    }
                }
            }
        }

        return response()->json(['status' => 1]);
    }

    public function campaign_step_settings(Request $request)
    {
        $conditions = CampaignStepsSettings::where('campaign_step_id', $request->step_id)->get();

        $conditions_html = view('campaigns.template._settings', compact('conditions'))->render();

        return response()->json(['conditions' => $conditions_html, 'count' => count($conditions)]);
    }

    public function remove_condition(Request $request)
    {
        $condition = CampaignStepsSettings::find($request->id);
        $step = CampaignSteps::find($condition->campaign_step_id);
        if (isset($step->id)) {
            $campaign = Campaign::find($step->campaign_id);
            if (isset($campaign->id)) {
                $linked_campaigns = json_decode($campaign->linked_campaigns);
                if (count($linked_campaigns)) {
                    foreach ($linked_campaigns as $camp) {
                        $link_step = CampaignSteps::where([
                            'campaign_id' => $camp,
                            'step_number' => $step->step_number
                        ])->first();

                        $condition = CampaignStepsSettings::where('campaign_step_id', $request->id)->delete();
                    }
                }
            }
        }

        return response()->json(['status' => 1, 'message' => 'Condition deleted successfully']);
    }

    protected function update_main_bounces($email)
    {
        $ch = curl_init();
        $url = 'http://157.230.141.165/bounces.php';

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            "id=&email=$email"
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = json_decode(curl_exec($ch));

        // Log::info('Bounce Updated to main:- ' . json_encode($server_output));
    }

    public function cohost_api()
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.cloudflare.com/client/v4/accounts/account_identifier/intel/domain-history",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "X-Auth-Key: hE1uRtUx1E3c0RaE7W_63Xlqq4v1_U3aXOcobTzQ",
                "Content-Type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            echo $response;
        }

        // $message_data = [
        //     'to' => [
        //         'sukhwindersodhi62@gmail.com',
        //     ],
        //     'from' => 'hello@haystraws.tech',
        //     'sender' => 'hello@haystraws.tech',
        //     'subject' => 'Test from api',
        //     'html_body' => '<h3>Test from api</h3>',
        // ];

        // $url = 'https://cohost.email/api/v1/send/message';
        // $proxy = 'socks://38.152.13.166:1080';
        // $headers = [
        //     'content-type' => 'application/json',
        //     'X-Server-API-Key' => 'Er0Drl784bzYRPyqbEr4pjT9'
        // ];

        // $client = new Client();

        // $response = $client->post($url, [
        //     'headers' => $headers,
        //     'proxy' => $proxy,
        //     'json' => $message_data,
        // ]);
        // echo "<pre>";
        // print_r($response->getBody()->getContents());
        // die;
    }

    public function custom_imap()
    {
        $mailbox2 = array(
            'mailbox'  => '{mail.gandi.net:993/imap/ssl}INBOX',
            'username' => 'noreply@emailpanther.digital',
            'password' => 'import3891'
        );

        $inbox = imap_open($mailbox2['mailbox'], $mailbox2['username'], $mailbox2['password']);
        if ($inbox) {
            // Search for all unseen messages (new messages)
            $emails = imap_search($inbox, 'UNSEEN');

            echo count($emails);
            die;

            if ($emails) {
                foreach ($emails as $email_number) {
                    // Fetch the email header and body
                    $header = imap_headerinfo($inbox, $email_number);
                    $body = imap_fetchbody($inbox, $email_number, 1);

                    // Process the email as needed
                    echo "From: " . $header->fromaddress . "<br>";
                    echo "Subject: " . $header->subject . "<br>";
                    echo "Body: " . $body . "<br>";

                    // Mark the message as read (optional)
                    imap_setflag_full($inbox, $email_number, "\\Seen");
                }
            } else {
                echo "No new messages.";
            }

            // Close the IMAP connection
            imap_close($inbox);
        } else {
            echo "Unable to connect to the IMAP server.";
        }
    }
}
