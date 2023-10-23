<?php

namespace Acelle\Http\Controllers;

use Illuminate\Http\Request;
use Acelle\Http\Controllers\Controller;
use Acelle\Model\SendingServer;
use Acelle\Model\Sender;
use Acelle\Library\Facades\Hook;
use Acelle\Model\DomainAssignment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use DB;

class DomainAssignmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $items = $request->user()->customer->domainAssignment()->search($request->keyword)
            ->filter($request);

        return view('domain_assignment.index', [
            'items' => $items,
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function listing(Request $request)
    {

        $items = $request->user()->customer->domainAssignment()->search($request->keyword)
            ->filter($request)
            ->orderBy($request->sort_order, $request->sort_direction ? $request->sort_direction : 'asc')
            ->paginate($request->per_page);

        return view('domain_assignment._list', [
            'items' => $items,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

        return view('domain_assignment.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // New sending server
        list($validator, $assignment) = DomainAssignment::createFromArray(array_merge($request->all(), [
            'customer_id' => $request->user()->customer->id,
        ]));

        // Failed
        if ($validator->fails()) {
            if ($assignment->isExtended()) {
                // Redirect to plugin's create page
                return redirect()->back()->withErrors($validator)->withInput();
            } else {
                return redirect()->action('DomainAssignmentController@create')
                    ->withErrors($validator)->withInput();
            }
        }

        // Success
        $request->session()->flash('alert-success', 'Domain created for assignment');

        return redirect()->action('DomainAssignmentController@index');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function storeNew(Request $request)
    {
        // authorize
        if (!$request->user()->customer->can('create', SendingServer::class)) {
            return $this->notAuthorized();
        }

        // New sending server
        list($server) = SendingServer::createMultiFromArray(array_merge($request->all(), [
            'customer_id' => $request->user()->customer->id,
        ]));

        $server->addIdentity(strtolower($request->domain));

        // Success
        $request->session()->flash('alert-success', trans('messages.sending_server.created'));

        // Redirect to Edit page
        if ($server->isExtended()) {
            return redirect($server->getEditUrl());
        } else {
            return redirect()->action('SendingServerController@edit', [$server->uid, $server->type]);
        }
    }

    public function new_multi_server(Request $request)
    {
        $main_server = SendingServer::findByUid($request->uid);
        if (count($main_server->multi_servers())) {
            $main_server->status = 'active';
            $main_server->save();
        }
        if ($request->isMethod('post')) {
            $options = json_decode($main_server->options);
            if (count((array) $options->identities)) {
                foreach ($options->identities as $d => $identities) {
                    $server_ip = isset($identities->server_ip) ? $identities->server_ip : '';
                    $domain = $d;
                    if ($server_ip)
                        break;
                }
            }
            if ($server_ip) {
                list($server) = SendingServer::createMultiFromArray(array_merge($request->all(), [
                    'customer_id' => $request->user()->customer->id,
                    'type' => 'multi-smtp',
                    'domain' => $domain
                ]));

                $server->multi_server = 1;
                $server->multi_server_linked_with = $main_server->id;

                $server->addIdentity(strtolower($request->default_from_email));
                $server->domain_created_attached = 1;
                $server->name = $request->default_from_email;
                $server->default_from_email = $request->default_from_email;
                $server->save();
                $server->logger()->info("New Sending server added. " . $server->uid);

                $new_options = json_decode($main_server->options);
                $ee_account = $server->install_ee($server_ip);
                $new_options->identities->{$domain}->proxy_created = 1;
                $new_options->identities->{$domain}->proxy_account = $ee_account;
                $server->logger()->info("New Sending server EE added. " . $ee_account);

                if ($new_options) {
                    $server->setOptions($new_options);
                }
                $server->status = 'active';
                $server->save();


                $sender = new Sender();
                $sender->name = $request->default_from_email;
                $sender->email = $request->default_from_email;
                $sender->customer_id = $request->user()->customer->id;
                $sender->status = Sender::STATUS_VERIFIED;
                $sender->sending_server_id = $server->id;
                $sender->save();


                $request->session()->flash('alert-success', 'Server added successfully');
                return back()->with('success', 'Server added successfully');
            }
            $request->session()->flash('alert-error', 'Server IP not found');
            return back()->with('success', 'Server added successfully');
        }
        return view('sending_servers.form._smtp_imap_speed', ['server' => $main_server]);
    }

    public function updatesendingLimit(Request $request)
    {
        $server = SendingServer::findByUid($request->uid);
        if ($request->limit != 'custom' && $request->limit != 'current') {
            $limits = SendingServer::sendingLimitValues()[$request->limit];
            $server->quota_value = $limits['quota_value'];
            $server->quota_unit = $limits['quota_unit'];
            $server->quota_base = $limits['quota_base'];
        } else {
            $server->quota_value = $request->quota_value;
            $server->quota_base = $request->quota_base;
            $server->quota_unit = $request->quota_unit;
        }
        $server->setOption('sending_limit', $request->limit);
        $server->save();

        return response()->json(['status' => 'success']);
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
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $server = SendingServer::findByUid($id);
        $server = SendingServer::mapServerType($server);
        // authorize
        if (!$request->user()->customer->can('update', $server)) {
            return $this->notAuthorized();
        }

        // bounce / feedback hanlder nullable
        if ($request->old() && empty($request->old()["bounce_handler_id"])) {
            $server->bounce_handler_id = null;
        }
        if ($request->old() && empty($request->old()["feedback_loop_handler_id"])) {
            $server->feedback_loop_handler_id = null;
        }

        $server->fill($request->old());

        $notices = [];

        try {
            $server->test();
            $server->syncIdentities();
            $server->setDefaultFromEmailAddress();
        } catch (\Exception $ex) {
            $server->disable();

            $notices[] = [
                'title' => trans('messages.sending_server.connect_failed'),
                'message' => $ex->getMessage()
            ];
        }

        $identities = [];
        $allIdentities = [];

        try {
            $identities = $server->getVerifiedIdentities();
            $allIdentities = array_key_exists('identities', $server->getOptions()) ? $server->getOptions()['identities'] : [];
        } catch (\Exception $ex) {
            $notices[] = [
                'title' => trans('messages.sending_server.identities_list_failed'),
                'message' => $ex->getMessage(),
            ];
        }

        // options
        if (isset($request->old()['options'])) {
            $server->options = json_encode($request->old()['options']);
        }

        $bigNotices = Hook::execute('generate_big_notice_for_sending_server', [$server]);

        return view('sending_servers.edit', [
            'server' => $server,
            'bigNotices' => $bigNotices,
            'notices' => $notices,
            'identities' => $identities,
            'allIdentities' => $allIdentities,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Get current user
        $current_user = $request->user();
        $server = SendingServer::findByUid($id);
        $server = SendingServer::mapServerType($server);

        // authorize
        if (!$request->user()->customer->can('update', $server)) {
            return $this->notAuthorized();
        }

        // save posted data
        if ($request->isMethod('patch')) {
            // $server->update_ee($request->all());
            // Save current user info
            $server->fill($request->all());
            if ($request->options) {
                $server->setOptions($request->options); // options = json_encode($request->options);
            }
            // validation
            $validator = $server->validConnection($request->all());

            if ($validator->fails()) {
                if ($server->isExtended()) {
                    return redirect($server->getEditUrl())->withErrors($validator)
                        ->withInput();
                } else {
                    return redirect()->action('SendingServerController@edit', [$server->uid, $server->type])
                        ->withErrors($validator)
                        ->withInput();
                }
            }

            // bounce / feedback hanlder nullable
            if (empty($request->bounce_handler_id)) {
                $server->bounce_handler_id = null;
            }
            if (empty($request->feedback_loop_handler_id)) {
                $server->feedback_loop_handler_id = null;
            }

            $server->addIdentity(strtolower($request->default_from_email));
            $server->domain_created_attached = 0;
            $server->name = $request->default_from_email;
            $server->default_from_email = $request->default_from_email;
            $server->save();

            $sender = new Sender();
            $sender->name = $request->default_from_email;
            $sender->email = $request->default_from_email;
            $sender->customer_id = $request->user()->customer->id;
            $sender->status = Sender::STATUS_VERIFIED;
            $sender->sending_server_id = $server->id;
            $sender->save();

            if ($server->save()) {
                $request->session()->flash('alert-success', trans('messages.sending_server.updated'));

                if ($server->isExtended()) {
                    return redirect($server->getEditUrl());
                } else {
                    return redirect()->action('SendingServerController@edit', [$server->uid, $server->type]);
                }
            }
        }
    }

    /**
     * Custom sort items.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function sort(Request $request)
    {
        echo trans('messages._deleted_');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {
        $items = SendingServer::whereIn(
            'uid',
            is_array($request->uids) ? $request->uids : explode(',', $request->uids)
        );

        foreach ($items->get() as $item) {
            // authorize
            if ($request->user()->customer->can('delete', $item)) {
                $item->doDelete();
            }
        }

        if ($request->ajax()) {
            // Redirect to my lists page
            echo trans('messages.sending_servers.deleted');
        } else {
            $request->session()->flash('alert-success', 'Server deleted successfully');
            return back()->with('success', 'Server deleted successfully');
        }
    }

    /**
     * Disable sending server.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function disable(Request $request)
    {
        $items = SendingServer::whereIn(
            'uid',
            is_array($request->uids) ? $request->uids : explode(',', $request->uids)
        );

        foreach ($items->get() as $item) {
            // authorize
            if ($request->user()->customer->can('disable', $item)) {
                $item->disable();
            }
        }

        // Redirect to my lists page
        echo trans('messages.sending_servers.disabled');
    }

    /**
     * Disable sending server.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function enable(Request $request)
    {
        $items = SendingServer::whereIn(
            'uid',
            is_array($request->uids) ? $request->uids : explode(',', $request->uids)
        );

        foreach ($items->get() as $item) {
            // authorize
            if ($request->user()->customer->can('enable', $item)) {
                $item->enable();
            }
        }

        // Redirect to my lists page
        echo trans('messages.sending_servers.enabled');
    }

    /**
     * Test Sending server.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function test(Request $request, $uid)
    {
        // Get current user
        $current_user = $request->user();

        // Fill new server info
        if ($uid) {
            $server = SendingServer::findByUid($uid);
        } else {
            $server = new SendingServer();
            $server->uid = 0;
        }

        $server->fill($request->all());

        // authorize
        if (!$current_user->customer->can('test', $server)) {
            return $this->notAuthorized();
        }

        if ($request->isMethod('post')) {
            // @todo testing method and return result here. Ex: echo json_encode($server->test())
            try {
                $server->mapType()->sendTestEmail([
                    'from_email' => $request->from_email,
                    'to_email' => $request->to_email,
                    'subject' => $request->subject,
                    'plain' => $request->content
                ]);
            } catch (\Exception $ex) {
                return response()->json([
                    'status' => 'error', // or success
                    'message' => $ex->getMessage()
                ], 401);
                return;
            }

            return response()->json([
                'status' => 'success', // or success
                'message' => trans('messages.sending_server.test_email_sent')
            ]);
            return;
        }

        return view('sending_servers.test', [
            'server' => $server,
        ]);
    }

    /**
     * Test Sending server.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function testConnection(Request $request, $uid)
    {
        $server = SendingServer::findByUid($uid);
        $server = SendingServer::mapServerType($server);

        // authorize
        if (!$request->user()->customer->can('update', $server)) {
            return $this->notAuthorized();
        }

        try {
            $server->test();

            return trans('messages.sending_server.test_success');
        } catch (\Exception $e) {
            $server->disable();

            return $e->getMessage();
        }
    }

    /**
     * Sending Limit Form.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function sendingLimit(Request $request)
    {
        if (!$request->uid) {
            $server = new SendingServer();
        } else {
            $server = SendingServer::findByUid($request->uid);
        }

        $server->fill($request->all());

        // Default quota
        if ($server->quota_value == -1) {
            $server->quota_value = '1';
            $server->quota_base = '3';
            $server->quota_unit = 'minute';
            $server->setOption('sending_limit', 'current');
        }

        // save posted data
        if ($request->isMethod('post')) {
            $selectOptions = $server->getSendingLimitSelectOptions();
            $server->save();


            return view('sending_servers.form._sending_limit', [
                'quotaValue' => $request->quota_value,
                'quotaBase' => $request->quota_base,
                'quotaUnit' => $request->quota_unit,
                'server' => $server,
                'no_label' => 1
            ]);
        }

        return view('sending_servers.form.sending_limit', [
            'server' => $server,
        ]);
    }

    /**
     * Save sending server config settings.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function config(Request $request, $uid)
    {
        // find server
        $server = SendingServer::findByUid($uid)->mapType();

        // authorize
        if (!$request->user()->customer->can('update', $server)) {
            return $this->notAuthorized();
        }

        // Save current user info
        $server->fill($request->all());

        // default sever quota
        if ($request->options) {
            $server->setOptions($request->options); // options = json_encode($request->options);
            $server->updateIdentitiesList($request->options);
        }

        // Sening limit
        if ($request->options['sending_limit'] != 'custom' && $request->options['sending_limit'] != 'current') {
            $limits = SendingServer::sendingLimitValues()[$request->options['sending_limit']];
            $server->quota_value = $limits['quota_value'];
            $server->quota_unit = $limits['quota_unit'];
            $server->quota_base = $limits['quota_base'];
        }

        // save posted data
        $this->validate($request, $server->getConfigRules());

        // bounce / feedback hanlder nullable
        if (empty($request->bounce_handler_id)) {
            $server->bounce_handler_id = null;
        }
        if (empty($request->feedback_loop_handler_id)) {
            $server->feedback_loop_handler_id = null;
        }

        if ($server->save()) {
            $request->session()->flash('alert-success', trans('messages.sending_server.updated'));

            if ($server->isExtended()) {
                return redirect($server->getEditUrl());
            } else {
                return redirect()->action('SendingServerController@edit', [$server->uid, $server->type]);
            }
        }
    }

    /**
     * Sending Limit Form.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function awsRegionHost(Request $request)
    {
        if ($request->uid) {
            $server = SendingServer::findByUid($request->uid);
        } else {
            $server = new SendingServer();
        }

        foreach (SendingServer::awsRegionSelectOptions() as $option) {
            if (isset($option['host']) && $option['value'] == $request->aws_region) {
                $server->host = $option['host'];
            }
        }
        return view('sending_servers.form._aws_region_host', [
            'server' => $server,
        ]);
    }

    /**
     * Add domain to sending server.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function addDomain(Request $request, $uid)
    {
        $server = SendingServer::findByUid($request->uid);

        // save posted data
        if ($request->isMethod('post')) {
            $valid = true;

            if (checkEmail($request->domain)) {
                // validation
                $validator = \Validator::make($request->all(), [
                    'name' => 'required',
                    'domain' => 'required|email',
                ]);

                if (in_array(strtolower($request->domain), $server->getVerifiedIdentities())) {
                    $validator->errors()->add('domain', trans('messages.sending_identity.exist_error'));
                    $valid = false;
                }

                if (!$valid || $validator->fails()) {
                    return redirect()->action('SendingServerController@addDomain', $server->uid)
                        ->withErrors($validator)
                        ->withInput();
                }
            } else {
                // validation
                $validator = \Validator::make($request->all(), [
                    'name' => 'required',
                    'domain' => 'required|regex:/^(?:[-A-Za-z0-9]+\.)+[A-Za-z]{2,6}$/i',
                ]);

                if (in_array(strtolower($request->domain), $server->getVerifiedIdentities())) {
                    $validator->errors()->add('domain', trans('messages.sending_identity.exist_error'));
                    $valid = false;
                }

                if (!$valid || $validator->fails()) {
                    return redirect()->action('SendingServerController@addDomain', $server->uid)
                        ->withErrors($validator)
                        ->withInput();
                }
            }

            $server->addIdentity(strtolower($request->domain));
            $server->domain_created_attached = 0;
            $server->save();

            $sender = new Sender();
            $sender->name = $request->name;
            $sender->email = $request->domain;
            $sender->customer_id = $request->user()->customer->id;
            $sender->status = Sender::STATUS_VERIFIED;
            $sender->sending_server_id = $server->id;
            $sender->save();

            $request->session()->flash('alert-success', trans('messages.sending_server.updated'));
            return;
        }

        return view('sending_servers.add_domain', [
            'server' => $server,
        ]);
    }

    /**
     * Remove domain from sending server.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function removeDomain(Request $request, $uid, $identity)
    {
        $server = SendingServer::findByUid($request->uid)->mapType();
        $server->remove_droplet($server->options, base64_decode($identity));
        $server->removeIdentity(base64_decode($identity));

        $request->session()->flash('alert-success', trans('messages.sending_server.domain.removed'));
        if ($server->isExtended()) {
            return redirect($server->getEditUrl());
        } else {
            return redirect()->action('SendingServerController@edit', [$server->uid, $server->type]);
        }
    }

    /**
     * Dropbox list.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function fromDropbox(Request $request)
    {
        $server = SendingServer::findByUid($request->uid);

        $droplist = $server->verifiedIdentitiesDroplist(strtolower(trim($request->keyword)));
        return response()->json($droplist);
    }

    public function attachNamecheap($domain, $server_ip)
    {
        $domain = explode('.', $domain);
        $server =  Http::get(env('NAMECHEAP_LIVE') . '&Command=namecheap.domains.dns.getHosts&ClientIp=135.181.86.205&SLD=' . $domain[0] . '&TLD=' . $domain[1]);
        $xmlObject = simplexml_load_string($server->body());

        $json = json_encode($xmlObject);
        $hosts = json_decode($json, true);

        $query = array();
        $count = 1;
        if (isset($hosts['CommandResponse']['DomainDNSGetHostsResult']['host']) && count($hosts['CommandResponse']['DomainDNSGetHostsResult']['host'])) {
            foreach ($hosts['CommandResponse']['DomainDNSGetHostsResult']['host'] as $key => $data) {
                if ($data['@attributes']['Type'] != 'A') {
                    $query[$key]["HostName$count"] = $data['@attributes']['Name'];
                    $query[$key]["RecordType$count"] = $data['@attributes']['Type'];
                    $query[$key]["Address$count"] = $data['@attributes']['Address'];
                    $query[$key]["MXPref$count"] = $data['@attributes']['MXPref'];
                    // $query[$key]["TTL$count"] = $data['@attributes']['TTL'];
                    $count++;
                }
            }
        }
        if (!isset($key))
            $key = 0;
        else
            ++$key;

        $query[$key]["HostName$count"] = '@';
        $query[$key]["RecordType$count"] = 'A';
        $query[$key]["Address$count"] = $server_ip;
        $query[$key]["MXPref$count"] = 10;

        // echo "<pre>";
        // print_r($query); die;

        // Log::channel('domain_process')->info(json_encode($query));

        $string = '';
        foreach ($query as $query_string) {
            $string .= http_build_query($query_string) . '&';
        }

        //echo $string; die;

        $server =  Http::get(env('NAMECHEAP_LIVE') . '&Command=namecheap.domains.dns.setHosts&ClientIp=135.181.86.205&SLD=' . $domain[0] . '&TLD=' . $domain[1] . '&' . $string);

        // Log::channel('domain_process')->info($server->body());
        // echo "<pre>";
        // print_r($server->body());
        // die;
    }
    public function error_logs(Request $request)
    {

        // EE queue
        // $response = Http::withHeaders([
        //     'Authorization' => 'Bearer ' . env('EE_AUTH'),
        //     'content-type' => 'application/json'
        // ])->get(env('EE_BASE') . "/outbox");
        // echo "<pre>";
        // print_r($response->json()); die;

        $logs = DB::table('connection_logs')
            ->select('connection_logs.*', 'sending_servers.name', 'sending_servers.uid')
            ->join('sending_servers', 'sending_servers.id', '=', 'connection_logs.sending_server')
            ->where("connection_logs.error_type", "2")
            // ->whereDate('connection_logs.created_at', date('Y-m-d'))
            ->orderby('connection_logs.created_at', 'desc')->get();
        return view('sending_servers.error_logs', compact('logs'));
    }
}
