<?php

namespace Acelle\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Acelle\Model\DomainAssignment;
use Acelle\Model\User;
use DB;

class DomainAssignmentProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:assignment';

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
        $domains = DomainAssignment::where('status', 'queued')->orwhere('status', 'processing')
            // ->orwhere('status', 'fail')
            ->orderby('attempt', 'asc')
            ->orderby('created_at', 'asc')
            ->get();
        if (count($domains)) {
            foreach ($domains as $domain) {
                if (isset($domain->id)) {
                    $domain->logger()->info('Processing Domain ' . $domain->domain);
                    try {

                        $domain->status = 'processing';
                        $domain->save();
                        $this->login($domain);


                        if (!$domain->dkim_name || !$domain->dkim_value || !$domain->request_id) {
                            list($dkim_name, $dkim_value, $request_id) = $this->add($domain);
                            $domain->dkim_name = $dkim_name;
                            $domain->dkim_value = $dkim_value;
                            $domain->request_id = $request_id;
                            $domain->save();
                        }

                        if ($domain->dkim_name && $domain->dkim_value && $domain->request_id) {
                            if ($domain->dns_attached == 0) {
                                if ($this->update_records($domain)) {
                                    $domain->dns_attached = 1;
                                    $domain->save();
                                    $verify = $this->verify($domain);
                                    if ($verify == 'fail' && $domain->attempt <= 20) {
                                        $domain->status = 'queued';
                                        $domain->attempt += $domain->attempt;
                                        $domain->save();
                                    } else {
                                        $domain->status = $verify;
                                        $domain->save();
                                    }
                                } else {
                                    $domain->status = 'fail';
                                    $domain->error = 'Error updating DNS records please check logs for more details.';
                                    $domain->save();
                                }
                            } elseif ($domain->dns_attached == 1) {
                                $verify = $this->verify($domain);
                                if ($verify == 'fail' && $domain->attempt <= 3) {
                                    $domain->status = 'queued';
                                    $domain->attempt += $domain->attempt;
                                    $domain->save();
                                } else {
                                    $domain->status = $verify;
                                    $domain->save();
                                }
                            }
                        }
                        //code...
                    } catch (\Throwable $th) {
                        $domain->logger()->info($th);
                        $domain->status = 'fail';
                        $domain->error = $th->getMessage();
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

    protected function add($domain)
    {
        $params = [
            'postal_server' => $domain->getPostalServer->postal_server,
            'postal_username' => $domain->getPostalServer->postal_username,
            'postal_password' => $domain->getPostalServer->postal_password,
            'postal_host' => $domain->getPostalServer->postal_host,
            'postal_organization' => $domain->getPostalServer->postal_organization,
            'postal_server' => $domain->getPostalServer->postal_server,
            'domain' => $domain->domain,
        ];

        $domain->logger()->info('Adding domain to postal server ' . json_encode($params));

        $data =  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->auth,
            'content-type' => 'application/json'
        ])->post($this->url . "postal/domain/add", $params);

        $result =  $data->json();

        $domain->logger()->info('Postal server response:- ' . json_encode($result));
        if ($data->status() == 200) {
            $domain->logger()->info('Postal server added:- ' . json_encode($result));

            if ($result['status'] == 'success') {
                $dkim_name = $result['data']['domain_key'];
                $dkim_value = str_replace('\/', '/', $result['data']['domain_key_content']);
                $request_id = $result['data']['domain_id'];

                return [$dkim_name, $dkim_value, $request_id];
            } else {
                $domain->status = 'fail';
                $domain->error = isset($result['message']) ? $result['message'] : '';
                $domain->save();
            }
        } elseif ($data->status() == 422) {
            $domain->logger()->info('Error adding postal server:- ' . json_encode($result));
            $this->login($domain, 1);
            $this->add($domain);
        } else {
            $domain->status = 'fail';
            $domain->error = isset($result['message']) ? $result['message'] : '';
            $domain->save();
        }
    }

    protected function verify($domain)
    {

        $params = [
            'postal_server' => $domain->getPostalServer->postal_server,
            'postal_username' => $domain->getPostalServer->postal_username,
            'postal_password' => $domain->getPostalServer->postal_password,
            'postal_host' => $domain->getPostalServer->postal_host,
            'postal_organization' => $domain->getPostalServer->postal_organization,
            'postal_server' => $domain->getPostalServer->postal_server,
            'domain_id' => $domain->request_id,
        ];

        $domain->logger()->info('Verifying domain:- ' . json_encode($params));

        $data =  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->auth,
            'content-type' => 'application/json'
        ])->post($this->url . "postal/domain/verify", $params);

        if ($data->status() == 200) {
            $domain->logger()->info('Domain verified:- ' . json_encode($data->json()));
            $result =  $data->json();

            return $result['status'];
        } elseif ($data->status() == 422) {
            $this->login($domain, 1);
            $this->verify($domain);
        } else {
            $domain->logger()->info('Error verifying:- ' . json_encode($data->json()));
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
}
