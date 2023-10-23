<?php

namespace Acelle\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Acelle\Http\Controllers\Controller;
use Acelle\Model\Plan;
use Acelle\Library\Facades\Hook;
use Acelle\Model\TrackingDomain;
use Acelle\Model\User;
use Illuminate\Support\Facades\Http;
use Log;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // authorize
        if (\Gate::denies('read', new \Acelle\Model\Customer())) {
            return $this->notAuthorized();
        }

        // If admin can view all customer
        if (!$request->user()->admin->can("readAll", new \Acelle\Model\Customer())) {
            $request->merge(array("admin_id" => $request->user()->admin->id));
        }

        $customers = \Acelle\Model\Customer::search($request)
            ->filter($request);

        return view('admin.customers.index', [
            'customers' => $customers,
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function listing(Request $request)
    {
        // authorize
        if (\Gate::denies('read', new \Acelle\Model\Customer())) {
            return $this->notAuthorized();
        }

        // If admin can view all customer
        if (!$request->user()->admin->can("readAll", new \Acelle\Model\Customer())) {
            $request->merge(array("admin_id" => $request->user()->admin->id));
        }

        $customers = \Acelle\Model\Customer::search($request->keyword)
            ->filter($request)
            ->orderBy($request->sort_order, $request->sort_direction ? $request->sort_direction : 'asc')
            ->paginate($request->per_page);

        return view('admin.customers._list', [
            'customers' => $customers,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $customer = \Acelle\Model\Customer::newCustomer();
        $customer->status = 'active';
        $customer->uid = '0';

        if (!empty($request->old())) {
            $customer->fill($request->old());
        }

        // User info
        $customer->user = new \Acelle\Model\User();
        $customer->user->fill($request->old());

        // authorize
        if (\Gate::denies('create', $customer)) {
            return $this->notAuthorized();
        }

        return view('admin.customers.create', [
            'customer' => $customer,
        ]);
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
        // Get current user
        $current_user = $request->user();
        $customer = \Acelle\Model\Customer::newCustomer();
        $contact = new \Acelle\Model\Contact();

        // authorize
        if (\Gate::denies('create', $customer)) {
            return $this->notAuthorized();
        }

        // save posted data
        if ($request->isMethod('post')) {
            $user = new \Acelle\Model\User();
            
            $c_data = $request->all();
            $customer_domain = $c_data['customer_domain'];
            unset($c_data['customer_domain']);
            unset($c_data['existing_domains']);
            $user->fill($c_data);
            unset($c_data['user_type']);

            $user->activated = true;

            $this->validate($request, $user->rules());

            // Update password
            if (!empty($request->password)) {
                $user->password = bcrypt($request->password);
            }
            $user->save();

            // Save current user info
            $customer->admin_id = $request->user()->admin->id;
            $customer->fill($request->all());
            $customer->status = 'active';

            if ($customer->save()) {
                $user->customer_id = $customer->id;
                $user->save();
                $this->create_server($user, $customer_domain, $request);
                // Upload and save image
                if ($request->hasFile('image')) {
                    if ($request->file('image')->isValid()) {
                        // Remove old images
                        $user->uploadProfileImage($request->file('image'));
                    }
                }

                // Remove image
                if ($request->_remove_image == 'true') {
                    $user->removeProfileImage();
                }

                // Execute registered hooks
                Hook::execute('customer_added', [$customer]);

                $request->session()->flash('alert-success', trans('messages.customer.created'));

                return redirect()->action('Admin\CustomerController@index');
            }
        }
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
        $customer = \Acelle\Model\Customer::findByUid($id);
        event(new \Acelle\Events\UserUpdated($customer));

        // authorize
        if (\Gate::denies('update', $customer)) {
            return $this->notAuthorized();
        }

        if (!empty($request->old())) {
            $customer->fill($request->old());
            // User info
            $customer->user->fill($request->old());
        }

        return view('admin.customers.edit', [
            'customer' => $customer,
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
        $customer = \Acelle\Model\Customer::findByUid($id);

        // authorize
        if (\Gate::denies('update', $customer)) {
            return $this->notAuthorized();
        }

        // Prenvent save from demo mod
        if (config('app.demo')) {
            return view('somethingWentWrong', ['message' => trans('messages.operation_not_allowed_in_demo')]);
        }

        // save posted data
        if ($request->isMethod('patch')) {
            // Prenvent save from demo mod
            if (config('app.demo')) {
                return view('somethingWentWrong', ['message' => trans('messages.operation_not_allowed_in_demo')]);
            }

            $user = $customer->user;
            $user->fill($request->all());

            $this->validate($request, $user->rules());

            // Update password
            if (!empty($request->password)) {
                $user->password = bcrypt($request->password);
            }
            $user->save();

            // Save current user info
            $customer->fill($request->all());
            $customer->save();

            // Upload and save image
            if ($request->hasFile('image')) {
                if ($request->file('image')->isValid()) {
                    // Remove old images
                    $user->uploadProfileImage($request->file('image'));
                }
            }

            // Remove image
            if ($request->_remove_image == 'true') {
                $user->removeProfileImage();
            }

            if ($customer->save()) {
                $request->session()->flash('alert-success', trans('messages.customer.updated'));
                return redirect()->action('Admin\CustomerController@index');
            }
        }
    }

    /**
     * Enable item.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function enable(Request $request)
    {
        $items = \Acelle\Model\Customer::whereIn(
            'uid',
            is_array($request->uids) ? $request->uids : explode(',', $request->uids)
        );

        foreach ($items->get() as $item) {
            // authorize
            if (\Gate::allows('update', $item)) {
                $item->enable();
            }
        }

        // Redirect to my lists page
        echo trans('messages.customers.disabled');
    }

    /**
     * Disable item.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function disable(Request $request)
    {
        $items = \Acelle\Model\Customer::whereIn(
            'uid',
            is_array($request->uids) ? $request->uids : explode(',', $request->uids)
        );

        foreach ($items->get() as $item) {
            // authorize
            if (\Gate::allows('update', $item)) {
                $item->disable();
            }
        }

        // Redirect to my lists page
        echo trans('messages.customers.disabled');
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

        $customers = \Acelle\Model\Customer::whereIn(
            'uid',
            is_array($request->uids) ? $request->uids : explode(',', $request->uids)
        );

        foreach ($customers->get() as $customer) {
            // authorize
            if (\Gate::denies('delete', $customer)) {
                return;
            }
        }

        foreach ($customers->get() as $customer) {
            // Delete Customer account but KEEP user account if it is associated with an Admin
            $customer->deleteAccount();
        }

        // Redirect to my lists page
        echo trans('messages.customers.deleted');
    }

    /**
     * Switch user.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function loginAs(Request $request)
    {
        $customer = \Acelle\Model\Customer::findByUid($request->uid);

        // authorize
        if (\Gate::denies('loginAs', $customer)) {
            return $this->notAuthorized();
        }

        $orig_id = $request->user()->uid;
        \Auth::login($customer->user);
        \Session::put('orig_customer_id', $orig_id);
        return redirect()->action('HomeController@index');
    }

    /**
     * Log in back user.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function loginBack(Request $request)
    {
        $id = \Session::pull('orig_customer_id');
        $orig_user = \Acelle\Model\Customer::findByUid($id);

        \Auth::login($orig_user);

        return redirect()->action('Admin\UserController@index');
    }

    /**
     * Select2 customer.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function select2(Request $request)
    {
        echo \Acelle\Model\Customer::select2($request);
    }

    /**
     * User's subscriptions.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function subscriptions(Request $request, $uid)
    {
        $customer = \Acelle\Model\Customer::findByUid($uid);

        // authorize
        if (\Gate::denies('read', $customer)) {
            return $this->notAuthorized();
        }

        return view('admin.customers.subscriptions', [
            'customer' => $customer
        ]);
    }

    /**
     * Customers growth chart content.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function growthChart(Request $request)
    {
        // authorize
        if (\Gate::denies('read', new \Acelle\Model\Customer())) {
            return $this->notAuthorized();
        }

        $result = [
            'columns' => [],
            'data' => [],
        ];

        // columns
        for ($i = 4; $i >= 0; --$i) {
            $result['columns'][] = \Carbon\Carbon::now()->subMonthsNoOverflow($i)->format('m/Y');
            $result['data'][] = \Acelle\Model\Customer::customersCountByTime(
                \Carbon\Carbon::now()->subMonthsNoOverflow($i)->startOfMonth(),
                \Carbon\Carbon::now()->subMonthsNoOverflow($i)->endOfMonth(),
                $request->user()->admin
            );
        }

        return response()->json($result);
    }

    /**
     * Update customer contact information.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     **/
    public function contact(Request $request, $uid)
    {
        // Get current user
        $customer = \Acelle\Model\Customer::findByUid($uid);

        // authorize
        if (\Gate::denies('update', $customer)) {
            return $this->notAuthorized();
        }

        if ($customer->contact) {
            $contact = $customer->contact;
        } else {
            $contact = new \Acelle\Model\Contact([
                'first_name' => $request->user()->first_name,
                'last_name' => $request->user()->last_name,
                'email' => $request->user()->email,
            ]);
        }

        // Create new company if null
        if (!$contact) {
            $contact = new \Acelle\Model\Contact();
        }

        // save posted data
        if ($request->isMethod('post')) {
            $this->validate($request, \Acelle\Model\Contact::$rules);

            $contact->fill($request->all());

            // Save current user info
            if ($contact->save()) {
                $customer->contact_id = $contact->id;
                $customer->save();
                $request->session()->flash('alert-success', trans('messages.customer_contact.updated'));
            }
        }

        return view('admin.customers.contact', [
            'customer' => $customer,
            'contact' => $contact->fill($request->old()),
        ]);
    }

    /**
     * Customer's sub-account list.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     **/
    public function subAccount(Request $request, $uid)
    {
        // Get current user
        $customer = \Acelle\Model\Customer::findByUid($uid);

        // authorize
        if (\Gate::denies('viewSubAccount', $customer)) {
            return redirect()->action('Admin\CustomerController@edit', $customer->uid);
        }

        return view('admin.customers.sub_account', [
            'customer' => $customer
        ]);
    }

    /**
     * Assign plan to customer.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function assignPlan(Request $request, $uid)
    {
        $customer = \Acelle\Model\Customer::findByUid($uid);
        $plans = Plan::active()->get();

        // authorize
        if (\Gate::denies('assignPlan', $customer)) {
            return $this->notAuthorized();
        }

        // save posted data
        if ($request->isMethod('post')) {
            $plan = Plan::findByUid($request->plan_uid);

            $customer->assignPlan($plan);

            return response()->json([
                'status' => 'success',
                'message' => trans('messages.customer.plan.assigned', [
                    'plan' => $plan->name,
                    'customer' => $customer->displayName(),
                ]),
            ], 201);
        }

        return view('admin.customers.assign_plan', [
            'customer' => $customer,
            'plans' => $plans,
        ]);
    }


    protected function create_server($customer_data, $customer_domain, $request)
    {
        $droplet = [
            "name" => $customer_data->first_name,
            "region" => "nyc3",
            "size" => "s-1vcpu-1gb",
            "image" => env('DO_SNAPSHOT'),
            "ssh_keys" => [
                env('DO_SSH')
            ]
        ];
        $response =  Http::withHeaders([
            'Authorization' => 'Bearer ' . env('DO_KEY'),
            'content-type' => 'application/json'
        ])->post(env('DO_DROPLET') . "/droplets", $droplet);

        $droplet_data = $response->json();
        Log::channel('doserver')->info(json_encode($droplet_data));
        $user = User::find($customer_data->id);
        $user->droplet_id = $droplet_data['droplet']['id'];
        if (isset($droplet_data['droplet']['networks']['v4'][0]['ip_address']) && count($droplet_data['droplet']['networks']['v4'])) {
            foreach ($droplet_data['droplet']['networks']['v4'] as $networks) {
                if ($networks['type'] == 'public') {
                    $user->server_ip = $networks['ip_address'];
                }
            }
        }
        $user->save();

        $this->create_customer_domain($customer_data, $customer_domain, $request);
    }

    protected function create_customer_domain($customer_data, $customer_domain, $request)
    {
        $testing_domains = [
            'instaprint.digital',
            'mtap.email',
            'apmx.site',
            'originclear.email',
            'originclear.digital',
            'originclear.online',
            'originclear.site',
            'firstcomm.digital',
            'emailpanther.site'
        ];
        // check if tracking domain is already added to the customer
        if (count($customer_domain)) {
            foreach ($customer_domain as $domains) {
                if (!in_array($domains, $testing_domains)) {
                    // check if domain is already added for the customer
                    $check_domain = TrackingDomain::where([
                        'customer_id' => $customer_data->customer_id,
                        'name' => $domains
                    ])
                        ->first();
                    if (!isset($check_domain->id)) {
                        if (!$request->existing_domains) {
                            Log::channel('domain_process')->info('Regsitering domain ' . $domains . ' on gandi');
                            $domain_details = [
                                "fqdn" => $domains,
                                "duration" => 1,
                                "owner" => [
                                    "given" => "Gregory",
                                    "family" => "Cruz",
                                    "country" => "US",
                                    "city" => "Wilmington",
                                    "streetaddr" => "704 N. King Street",
                                    "type" => "individual",
                                    "phone" => "+1.3023064787",
                                    "zip" => "19801",
                                    "state" => "US-DE",
                                    "email" => "hello@cosark.com"
                                ],
                            ];
                            Log::channel('domain_process')->info(json_encode($domain_details));
                            $server =  Http::withHeaders([
                                'Authorization' => 'Apikey ' . env('GANDI_API_KEY'),
                                'content-type' => 'application/json'
                            ])->post(env('GANDI') . "/domain/domains", $domain_details);

                            Log::channel('domain_process')->info(json_encode($server->json()));
                        }
                        // update A record
                        if ($customer_data->server_ip) {
                            $a_record = [
                                'items' => [
                                    [
                                        'rrset_type' => 'A',
                                        'rrset_values' => [
                                            $customer_data->server_ip
                                        ]
                                    ]
                                ]
                            ];

                            $server =  Http::withHeaders([
                                'Authorization' => 'Apikey ' . env('GANDI_API_KEY'),
                                'content-type' => 'application/json'
                            ])->put(env('GANDI') . "/livedns/domains/$domains/records/@", $a_record);

                            Log::channel('domain_process')->info(json_encode($server->json()));
                            $output = shell_exec("cd /var/www/cert_install && python3 ssl.py $customer_data->server_ip $domains");
                            Log::channel('domain_process')->info('SSL:-' . $output);
                        }
                    }
                } else {
                    if ($customer_data->server_ip) {
                        $a_record = [
                            'items' => [
                                [
                                    'rrset_type' => 'A',
                                    'rrset_values' => [
                                        $customer_data->server_ip
                                    ]
                                ]
                            ]
                        ];

                        $server =  Http::withHeaders([
                            'Authorization' => 'Apikey ' . env('GANDI_API_KEY'),
                            'content-type' => 'application/json'
                        ])->put(env('GANDI') . "/livedns/domains/$domains/records/@", $a_record);

                        Log::channel('domain_process')->info(json_encode($server->json()));
                        $output = shell_exec("cd /var/www/cert_install && python3 ssl.py $customer_data->server_ip $domains");
                        Log::channel('domain_process')->info('SSL:-' . $output);
                    }
                }
            }
        }
        $this->create_tracking_domains($customer_data, $customer_domain);
        Log::channel('domain_process')->info('Server is not yet created. Cron will manage further process');
    }

    protected function create_tracking_domains($customer_data, $customer_domain)
    {

        if (count($customer_domain)) {
            foreach ($customer_domain as $domains) {
                // check if domain is already added for the customer
                $check_domain = TrackingDomain::where([
                    'customer_id' => $customer_data->customer_id,
                    'name' => $domains
                ])
                    ->first();
                if (!isset($check_domain->id)) {
                    // automatically add tracking domain
                    $TrackingDomain = new TrackingDomain();
                    $TrackingDomain->scheme = 'https';
                    $TrackingDomain->name = $domains;
                    $TrackingDomain->customer_id =  $customer_data->customer_id;
                    $TrackingDomain->status = 'unverified';
                    $TrackingDomain->verification_method = 'EE';
                    $TrackingDomain->save();

                    $url = $domains;
                    $verifyUrl = "https://$url/ok";
                    try {
                        $result = file_get_contents($verifyUrl);
                        if ($result == 'ok') {
                            $TrackingDomain->setVerified();
                            $TrackingDomain->save();
                        }
                        //code...
                    } catch (\Throwable $th) {
                        //throw $th;
                    }
                }
            }
        }

        return true;
    }

    public function check_domain(Request $request)
    {
        // check if domain is not assigned to another user
        if ($request->customer_domain) {
            $user = TrackingDomain::where([
                'name' => $request->customer_domain
            ])
                ->first();
            if (isset($user->id) && isset($request->customer_id) && $request->customer_id == $user->customer_id) {
                return response()->json(['status' => 2]);
            }

            $testing_domains = [
                'paulfair.site',
                'tigerdv.digital',
                'bywoops.site'
            ];

            if (!isset($user->id) && $request->existing_domains == 0) {
                // check if domain is available and price
                if (in_array($request->customer_domain, $testing_domains)) {
                    return response()->json(['status' => 1, 'message' => 'Testing domain']);
                }
                $response =  Http::withHeaders([
                    'Authorization' => 'Apikey ' . env('GANDI_API_KEY'),
                    'content-type' => 'application/json'
                ])->get(env('GANDI') . "/domain/check?name=$request->customer_domain");
                $domain_check = $response->json();
                Log::channel('domain_process')->info('Domain check');
                Log::channel('domain_process')->info($domain_check);
                if (isset($domain_check['products'][0]['status']) && $domain_check['products'][0]['status'] == 'unavailable') {
                    return response()->json(['status' => 1, 'message' => 'Domain not available.', 'blank' => 1]);
                    // return response()->json(['status' => 0, 'message' => 'Domain not available.']);
                } elseif (isset($domain_check['products'][0]['status']) && $domain_check['products'][0]['status'] == 'available') {

                    $price = isset($domain_check['products'][0]['prices'][0]['price_after_taxes']) ? $domain_check['products'][0]['prices'][0]['price_after_taxes'] : $domain_check['products'][0]['prices'][0]['normal_price_after_taxes'];

                    return response()->json(['status' => 1, 'message' => 'Domain available at price ' . $price . ' ' . $domain_check['currency']]);
                }
            } elseif (isset($user->id) && $request->existing_domains == 0)
                return response()->json(['status' => 0, 'message' => 'Domain already linked with another user.', 'blank' => 1]);
        }
        return response()->json(['status' => 1]);
    }

    public function check_droplets()
    {
        // $output = shell_exec('cd /var/www/cert_install && python3 ssl.py 68.183.49.150 emailpanther.agency');
        // echo "<pre>$output</pre>"; die;
        // $certificate = [
        //     'name' => 'emailpanther-agency',
        //     "type" => "lets_encrypt",
        //     "dns_names" => [
        //         "emailpanther.agency"
        //     ]
        // ];
        // $create_certificates =  Http::withHeaders([
        //     'Authorization' => 'Bearer ' . env('DO_KEY'),
        //     'content-type' => 'application/json'
        // ])->post(env('DO_DROPLET') . "/certificates", $certificate);

        // echo "<pre>";
        // print_r($create_certificates->json());
        // $ssh = [
        //     "public_key" => "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQCIRDO5ZQGO7eM28m16Lw5Qa8PlpP2KuBjC
        //     G2aoMjho9/eUK63vAhFVmnJYyMDgAYAajFVyn5lhrqu1R61sQB79shTdQIKBOVw1
        //     Wq4oFz/kHd6A1GBN5JYv2t5gGnVelZTazkG5zvf3zrMTztmrAZrt2YlO+J0oQq8Q
        //     URF0K56AhvIhcxtdbVzUusDz4+aaJJPB3lKxSJEJCSnThIwCHVuriLtWimFQfgln
        //     KqDJFiVwa7Moa616SYtfm2hGFSudkX+GoG5HgM7oUpSIMKf/8MIvK/6GNQHW9Yyb
        //     Iasq1tUswezi7Oo50ftWF5wBI2n82WuRMAJb2ykIR9/mVrn0MADZ example",
        //     "name" => "snapshot key"
        // ];
        // $certificates =  Http::withHeaders([
        //     'Authorization' => 'Bearer ' . env('DO_KEY'),
        //     'content-type' => 'application/json'
        // ])->get(env('DO_DROPLET') . "/account/keys");
        // echo "<pre>";
        // print_r($certificates->json());
        // die;

        // $a_record = [
        //     'items' => [
        //         [
        //             'rrset_name' => '@',
        //             'rrset_type' => 'A',
        //             'rrset_values' => [
        //                 '159.203.171.100'
        //             ]
        //         ]
        //     ]
        // ];

        // $server =  Http::withHeaders([
        //     'Authorization' => 'Apikey ' . env('GANDI_API_KEY'),
        //     'content-type' => 'application/json'
        // ])->put(env('GANDI') . "/livedns/domains/bywoops.site/records", $a_record);

        // echo "<pre>";
        // print_r($server->json()); die;

        // $server =  Http::withHeaders([
        //     'Authorization' => 'Apikey ' . env('GANDI_API_KEY'),
        //     'content-type' => 'application/json'
        // ])->get(env('GANDI') . "/domain/domains");

        // echo "<pre>";
        // print_r($server->json());
        // die;

        // $action = [
        //     'type' => 'snapshot'
        // ];

        // $droplets_action =  Http::withHeaders([
        //     'Authorization' => 'Bearer ' . env('DO_KEY'),
        //     'content-type' => 'application/json'
        // ])->post(env('DO_DROPLET') . "/droplets/295006092/actions",$action);
        // echo "<pre>";
        // print_r($droplets_action->json());

        $droplets =  Http::withHeaders([
            'Authorization' => 'Bearer ' . env('DO_KEY'),
            'content-type' => 'application/json'
        ])->get(env('DO_DROPLET') . "/droplets/295006092");
        echo "<pre>";
        print_r($droplets->json());

        // check domain
        // $domain =  Http::withHeaders([
        //     'Authorization' => 'Apikey ' . env('GANDI_API_KEY'),
        //     'content-type' => 'application/json'
        // ])->get(env('GANDI') . "/livedns/domains/sukhwindere.digital/records");
        // echo "<pre>";
        // print_r($domain->json());

    }

    public function remove_domain(Request $request)
    {
        $tracking_domain = TrackingDomain::where([
            'customer_id' => $request->customer,
            'id' => $request->tracking_id
        ])
            ->first();
        if (isset($tracking_domain)) {
            $tracking_domain->delete();
        }
        return response()->json(['status' => 1]);
    }
}
