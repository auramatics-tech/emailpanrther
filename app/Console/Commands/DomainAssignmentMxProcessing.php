<?php

namespace Acelle\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Acelle\Model\DomainAssignment;
use Acelle\Model\User;
use DB;

class DomainAssignmentMxProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domainmx:assignment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Domain assignment';

    public $url = '';
    public $auth = '';
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->url = 'http://91.107.236.248:8000/';
        $this->auth = '';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $domains = DomainAssignment::where('mx_route', '1')
            ->where(function ($query) {
                $query->where('mx_status', 'queued')
                    ->orwhere('mx_status', 'processing');
            })
            // ->where('dns_attached', 1)
            // ->where('id','>',333)
            // ->where('id','<',344)
            ->orderby('attempt', 'asc')
            ->orderby('created_at', 'asc')
            ->get();


        // echo "<pre>";
        // print_r($domains); die;

        if (count($domains)) {
            foreach ($domains as $domain) {
                if (isset($domain->id)) {
                    $domain->logger()->info('Processing Domain ' . $domain->domain);
                    try {
                        $this->login($domain);

                        if ($domain->dns_attached == 0) {
                            if ($this->update_records($domain)) {
                                $domain->dns_attached = 1;
                                $domain->save();
                            }
                        }

                        $domain->mx_status = 'processing';
                        $domain->save();

                        if ($domain->mx_route && !$domain->mx_domain_tracking_id) {
                            $domain->mx_domain_tracking_id = $this->add_mx($domain);
                            $domain->save();
                        }

                        if ($this->verify_mx($domain)) {
                            $domain->mx_status = 'success';
                            $domain->save();
                        }


                        //code...
                    } catch (\Throwable $th) {
                        $domain->logger()->info($th);
                        $domain->mx_status = 'fail';
                        $domain->mx_error = $th->getMessage();
                        $domain->save();
                    }
                }
            }
        }
    }

    protected function login($domain, $reset = 0)
    {
        $users = User::find($domain->customer_id);
        if ($users->domain_assignment_auth && !$reset) {
            $this->auth = $users->domain_assignment_auth;
            $domain->logger()->info('Login successfull ' . $this->auth);
        } else {

            $domain->logger()->info('Trying login');

            $params = [
                'username' => 'xtelepathy@gmail.com',
                'password' => 'XBhdGh5QGdtYWlsLmNvbSIsImlhdCI6M'
            ];

            $data =  Http::withHeaders([
                'content-type' => 'application/json'
            ])->post($this->url . "login", $params);

            if (isset($data->json()['authorization']) && $data->json()['authorization']) {

                $domain->logger()->info('Login successfull ' . $data->json()['authorization']);
                $users->domain_assignment_auth =  $data->json()['authorization'];
                $users->save();

                $this->auth = $users->domain_assignment_auth;
            } else {
                $domain->logger()->info('Login failed ' . json_encode($data->json()));
            }
        }
    }

    protected function add_mx($domain)
    {
        $params = [
            'mxrouting_info' => [
                'username' => 'anoketco',
                'password' => 'n&@38y=?SG@Z9E?4',
                'host' => 'monday.mxrouting.net:2222'
            ],
            'domain_info' => [
                'domain' => $domain->domain,
                'username' => 'hello',
                'password' => 'export3891',
            ]
        ];

        $domain->logger()->info('Adding MX Route domain to postal server ' . json_encode($params));

        $data =  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->auth,
            'content-type' => 'application/json'
        ])->post($this->url . "mxrouting/domain/add", $params);

        $result =  $data->json();

        echo "<pre>";
        print_r($result);

        echo $domain->domain;
        echo $data->status();
            

        $domain->logger()->info('MX server response:- ' . json_encode($result));
        if ($data->status() == 200) {
            $domain->logger()->info('MX server added:- ' . json_encode($result));

            if ($result['status'] == 'success') {
                // check imap connection using emailengine
                $domain->mx_domain_tracking_id = $result['tracking_id'];
                $domain->save();
                // $account = [
                //     'imap' => [
                //         'auth' => [
                //             'user' => $domain->domain,
                //             'pass' => 'export3891',
                //         ],
                //         "host" => 'monday.mxrouting.net',
                //         "port" => 993,
                //         "secure" => true,
                //         "resyncDelay" => 900
                //     ]
                // ];
                // $response = Http::withHeaders([
                //     'Authorization' => 'Bearer ' . env('EE_AUTH'),
                //     'content-type' => 'application/json'
                // ])->post(env('EE_BASE') . "/verifyAccount", $account);

                // $connection = $response->json();
                // if (isset($connection['imap']['error']) && $connection['imap']['error']) {
                //     $domain->mx_status = 'fail';
                //     $domain->mx_error = $connection['imap']['error'];
                //     $domain->save();
                // }else{
                    return isset($result['tracking_id']) ? $result['tracking_id'] : '';
                // }
            } else {
                $domain->mx_status = 'fail';
                $domain->mx_error = isset($result['message']) ? $result['message'] : '';
                $domain->save();
            }
        } elseif ($data->status() == 422) {
            $domain->logger()->info('Error adding postal server:- ' . json_encode($result));
            $this->login($domain, 1);
            $this->add_mx($domain);
        } else {
            $domain->mx_status = 'fail';
            $domain->mx_error = isset($result['message']) ? $result['message'] : '';
            $domain->save();
        }
    }

    protected function verify_mx($domain)
    {
        $params = [
            'tracking_id' => $domain->mx_domain_tracking_id
        ];

        $domain->logger()->info('Verifying mx:- ' . json_encode($params));

        $data =  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->auth,
            'content-type' => 'application/json'
        ])->post($this->url . "mxrouting/domain/verify", $params);

        if ($data->status() == 200) {
            $domain->logger()->info('MX verified:- ' . json_encode($data->json()));
            $result =  $data->json();

            return $result['status'];
        } elseif ($data->status() == 422) {
            $this->login($domain, 1);
            $this->verify_mx($domain);
        } else {
            $domain->logger()->info('MX verifying:- ' . json_encode($data->json()));
            return false;
        }
    }

    protected function update_records($domain)
    {
        $records = $this->records($domain);

        if ($domain->getDomainRegistrar->registrar == 'gandi') {
            return $this->update_records_gandi($domain, $records);
        } elseif ($domain->getDomainRegistrar->registrar == 'namecheap') {
            return $this->update_records_namecheap($domain, $records);
        } elseif ($domain->getDomainRegistrar->registrar == 'godaddy') {
            return $this->update_records_godaddy($domain, $records);
        }
    }

    protected function records($domain)
    {
        $records = [
            [
                'type' => 'TXT',
                'name' => '@',
                'value' => "v=spf1 a mx include:spf." . $domain->getPostalServer->postal_host . " ~all",
                'ttl' => 600
            ],
            [
                'type' => 'CNAME',
                'name' => 'psrp',
                'value' => "rp." . $domain->getPostalServer->postal_host,
                'ttl' => 600
            ],
            [
                'type' => 'TXT',
                'name' => strtolower($domain->dkim_name),
                'value' => str_replace('\/', '/', $domain->dkim_value),
                'ttl' => 600
            ]
        ];

        if ($domain->mx_route) {
            $mx_route = [
                [
                    'type' => 'MX',
                    'name' => '@',
                    'value' => "monday.mxrouting.net.",
                    'priority' => 10
                ],
                [
                    'type' => 'MX',
                    'name' => '@',
                    'value' => "monday-relay.mxrouting.net.",
                    'priority' => 20
                ]
            ];
        } else {
            $mx_route = [];
        }

        return $records = array_merge($records, $mx_route);
    }

    protected function update_records_gandi($domain, $records)
    {
        $domain->logger()->info('Updating Records Gandi:- ' . json_encode($records));
        foreach ($records as $record) {

            if ($record['type'] != 'MX') {
                if ($record['type'] == 'CNAME') {
                    $record['value'] = $record['value'] . ".";
                }
                $domain->logger()->info(json_encode($record));


                $gandirecords = [
                    'rrset_values' => [
                        $record['value']
                    ]
                ];

                $dns =  Http::withHeaders([
                    'Authorization' => 'Apikey ' . env('GANDI_API_KEY'),
                    'content-type' => 'application/json'
                ])->put(env('GANDI') . "/livedns/domains/$domain->domain/records/" . $record['name'] . "/" . $record['type'], $gandirecords);

                $domain->logger()->info($dns->status());
                if ($dns->status() != 201) {
                    $domain->logger()->info('Error updating record:- ' . json_encode($gandirecords));
                    $domain->logger()->info(json_encode($dns->json()));
                    return false;
                    break;
                }
            }
        }

        return true;
    }

    protected function update_records_namecheap($domain, $records)
    {
        $domain->logger()->info('Updating Records Namecheap:- ' . json_encode($records));
        $domain_data = explode('.', $domain->domain);
        $server =  Http::get(env('NAMECHEAP_LIVE') . '&Command=namecheap.domains.dns.getHosts&ClientIp=65.108.3.53&SLD=' . $domain_data[0] . '&TLD=' . $domain_data[1]);
        $xmlObject = simplexml_load_string($server->body());

        $json = json_encode($xmlObject);
        $hosts = json_decode($json, true);

        $query = array();
        $count = 1;

        if (isset($hosts['CommandResponse']['DomainDNSGetHostsResult']['host']) && count($hosts['CommandResponse']['DomainDNSGetHostsResult']['host'])) {
            foreach ($hosts['CommandResponse']['DomainDNSGetHostsResult']['host'] as $key => $data) {
                if ($data['@attributes']['Type'] != 'TXT' && $data['@attributes']['Type'] != 'CNAME' && $data['@attributes']['Type'] != 'MX') {
                    $query[$key]["HostName$count"] = $data['@attributes']['Name'];
                    $query[$key]["RecordType$count"] = $data['@attributes']['Type'];
                    $query[$key]["Address$count"] = $data['@attributes']['Address'];
                    $query[$key]["MXPref$count"] = $data['@attributes']['MXPref'];
                    $query[$key]["TTL$count"] = $data['@attributes']['TTL'];
                    $count++;
                }
            }
        }
        if (!isset($key))
            $key = 0;
        else
            ++$key;

        foreach ($records as $record) {
            $query[$key]["HostName$count"] = $record['name'];
            $query[$key]["RecordType$count"] = $record['type'];
            $query[$key]["Address$count"] = $record['value'];
            if ($record['type'] == 'MX') {
                $query[$key]["EmailType$count"] = 'MX';
                $query[$key]["MXPref$count"] = $record['priority'];
            }
            $key++;
            $count++;
        }

        $domain->logger()->info('Namecheap Records:- ' . json_encode($query));

        $string = '';
        foreach ($query as $query_string) {
            $string .= http_build_query($query_string) . '&';
        }

        echo env('NAMECHEAP_LIVE') . '&Command=namecheap.domains.dns.setHosts&ClientIp=65.108.3.53&SLD=' . $domain_data[0] . '&TLD=' . $domain_data[1] . '&' . $string; die;

        $namecheap =  Http::get(env('NAMECHEAP_LIVE') . '&Command=namecheap.domains.dns.setHosts&ClientIp=65.108.3.53&SLD=' . $domain_data[0] . '&TLD=' . $domain_data[1] . '&' . $string);

        echo "<br>";

        echo json_encode($namecheap->body());
        $domain->logger()->info('Namecheap Response:- ' . json_encode($namecheap->body()) . ' ' . $namecheap->status());
        if ($namecheap->status() != 200) {
            $domain->logger()->info('Error updating Records Namecheap:- ' . json_encode($namecheap->body()));
            return false;
        }

        return true;
    }

    protected function update_records_godaddy($domain, $records)
    {

        $domain->logger()->info('Updating Records Godaddy:- ' . json_encode($records));
        foreach ($records as $record) {
            $record['data'] = $record['value'];
            unset($record['value']);

            if (isset($record['priority']) && $record['priority'] == 20) {
                $dns =  Http::withHeaders([
                    'Authorization' => " sso-key " . env('GODADDY_KEY') . ":" . env('GODADDY_SECRET'),
                    'content-type' => 'application/json'
                ])->patch(env('GODADDY_URL') . "domains/$domain->domain/records/", [$record]);
            } else {
                $dns =  Http::withHeaders([
                    'Authorization' => " sso-key " . env('GODADDY_KEY') . ":" . env('GODADDY_SECRET'),
                    'content-type' => 'application/json'
                ])->put(env('GODADDY_URL') . "domains/$domain->domain/records/" . $record['type'] . "/" . $record['name'], [$record]);
            }
            if ($dns->status() != 200) {
                $domain->logger()->info('Error updating record:- ' . json_encode($record));
                $domain->logger()->info(json_encode($dns->json()));
                return false;
                break;
            }
        }
        return true;
    }
}
