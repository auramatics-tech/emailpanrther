<?php

namespace Acelle\Console\Commands;

use Acelle\Model\Proxies;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Acelle\Model\SendingServer;
use Acelle\Model\TrackingDomain;
use Acelle\Model\ConnectionLog;
use Acelle\Model\ServerLog;
use Log;

class LinkSendingServers extends Command
{


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'link:server';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Link sending servers';

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
     */
    public function handle()
    {
        try {
            $sending_servers = SendingServer::where(['domain_created_attached' => 0, 'multi_server_linked_with' => 0])->get();
            $tracking_ready = [];
            if (count($sending_servers)) {
                foreach ($sending_servers as $key => $servers) {
                    $delete_error = ConnectionLog::where('host', $servers->host)->delete();
                    $sending_identities = json_decode($servers->options);

                    if (isset($sending_identities->identities)) {
                        foreach ($sending_identities->identities as $domain_email => $data) {
                            $domain = $domain_email;
                            $this->info($domain_email);
                            $domain = (substr(strrchr($domain, "@"), 1)) ? substr(strrchr($domain, "@"), 1) : $domain_email;
                            // 65.108.3.53
                            $data->server_ip = '65.108.3.53'; //'127.0.0.1';

                            if (!isset($data->cloudfare_registered) || !$data->cloudfare_registered) {

                                $cloudfare = $this->register_domain_cloudfare($domain, $servers);
                                //  $updated = $this->cloudfare_dns_update($servers,$data->server_ip,$domain,$cloudfare['result']['id']);
                                //  echo "<prE>";print_r($updated);die;
                                if ($cloudfare) {
                                    $data->cloudfare_registered = 1;
                                }
                            }

                            if (isset($data->cloudfare_registered) && $data->cloudfare_registered) {
                                $this->info('Started');

                                if (!isset($data->dns_attached) || $data->dns_attached == 0) {
                                    // Attach server A record with domain
                                    $this->info('Updating DNS');
                                    if ($servers->domain_type == 'gandi') {
                                        if ($this->update_dns($domain, $data->server_ip, $servers)) {
                                            $data->dns_attached = 1;
                                        }
                                    } else if ($servers->domain_type == 'namecheap') {
                                        if ($this->update_dns_namecheap($domain, $data->server_ip, $servers)) {
                                            $data->dns_attached = 1;
                                        }
                                    } else if ($servers->domain_type == 'godaddy') {
                                        if ($this->update_dns_godaddy($domain, $data->server_ip, $servers)) {
                                            $data->dns_attached = 1;
                                        }
                                    }
                                    $this->cloudfare_dns_update($servers,$data->server_ip,$domain,$cloudfare['id']);
                                    $servers->save();
                                }

                                if ($servers->type == 'smtp') {
                                    if (!isset($data->proxy_created) || $data->proxy_created == 0) {
                                        if (isset($data->domain_created) && $data->domain_created == 1) {
                                            // Create EE Proxy
                                            $this->info('Creating EE Proxy');
                                            $ee_account = $this->create_ee_proxy($domain, $servers, $data->server_ip, $domain_email, $servers);
                                            if ($ee_account) {
                                                $servers->logger()->info('EE Proxy created' . $ee_account);
                                                $data->proxy_created = 1;
                                                $servers->ee_created = 1;
                                                $data->proxy_account = $ee_account;
                                            }
                                        }
                                    }
                                } else {
                                    $data->proxy_created = 0;
                                }

                                if (!$this->has_ssl($domain, $servers)) {

                                    $data->install_ssl = 0;
                                    $servers->ssl_check_count += 1;
                                    $servers->logger()->info('SSL Check failed Count ' . $servers->ssl_check_count);

                                    if ($servers->ssl_check_count == 10) {

                                        $servers->logger()->info('SSL Installation failed after 2 attempts please check manually.');
                                        // Send mail in this case
                                        if (isset($data->proxy_account) && $data->proxy_account)
                                            $this->error_mail($domain, $data->proxy_account, $servers->uid);
                                        $servers->ssl_installation_failed = 1;
                                    } elseif ($servers->ssl_check_count == 3) {
                                        $servers->logger()->info('Trying apache restart.');
                                    }
                                } else {
                                    $data->install_ssl = 1;
                                    $servers->logger()->info('SSL Installated');
                                }


                                if (!isset($data->tracking_ready) || $data->tracking_ready == 0) {
                                    // Create Tracking Domain
                                    $this->info('Creating Tracking Domain');
                                    if ($this->create_tracking_domains($domain, $servers->customer_id, $servers->id, $servers->host)) {
                                        $servers->logger()->info('Server ready to be used.');
                                        $this->info('Tracking Domain Ready');
                                        $data->tracking_ready = 1;
                                        $tracking_ready[] = 1;
                                        $servers->domain_created_attached = 1;
                                        $servers->status = 'active';
                                    } else {
                                        $servers->logger()->info('Domain open failed. Will try again');
                                    }
                                } else {
                                    $servers->logger()->info('Server ready to be used.');
                                    $servers->domain_created_attached = 1;
                                    $servers->status = 'active';
                                }
                            }
                            $sending_identities->identities->{$domain_email} = $data;
                        }
                        $servers->options = json_encode($sending_identities);
                        $servers->save();
                    }
                }
            }
            //code...
        } catch (\Throwable $th) {
            $servers->logger()->info('ERROR');
            $servers->logger()->info($th->getMessage());
            if (isset($servers->host)) {
                $error = new ConnectionLog();
                $error->host = $servers->host;
                $error->error = $th->getMessage();
                $error->error_type = '3';
                $error->sending_server = $servers->id;
                $error->save();
            }
        }
    }

    protected function update_dns($domain, $server_ip, $server)
    {
        $records = [
            'rrset_values' => [
                $server_ip
            ]
        ];
        $this->server_error_logs($server->id, 'DNS update started');
        $dns =  Http::withHeaders([
            'Authorization' => 'Apikey ' . env('GANDI_API_KEY'),
            'content-type' => 'application/json'
        ])->put(env('GANDI') . "/livedns/domains/$domain/records/@/A", $records);

        Log::channel('domain_process')->info(json_encode($dns->json()));
        $server->logger()->info('Updating DNS Gandi' . json_encode($dns->json()));

        // update nameservers
        $records = [
            'rrset_values' => [
                'ian.ns.cloudflare.com',
                'isla.ns.cloudflare.com'
            ]
        ];

        $dns =  Http::withHeaders([
            'Authorization' => 'Apikey ' . env('GANDI_API_KEY'),
            'content-type' => 'application/json'
        ])->put(env('GANDI') . "/livedns/domains/$domain/records/$domain/NS", $records);

        Log::channel('domain_process')->info(json_encode($dns->json()));
        $server->logger()->info('Updating DNS Gandi' . json_encode($dns->json()));
        $this->server_error_logs($server->id, 'DNS update done', json_encode($dns->json()));
        return true;
    }
    public function update_dns_namecheap($domain, $server_ip, $server_main)
    {
        $domain = explode('.', $domain);
        $this->server_error_logs($server_main->id, 'Namecheap DNS update started');
        $server =  Http::get(env('NAMECHEAP_LIVE') . '&Command=namecheap.domains.dns.getHosts&ClientIp=65.108.3.53&SLD=' . $domain[0] . '&TLD=' . $domain[1]);
        $xmlObject = simplexml_load_string($server->body());

        $json = json_encode($xmlObject);
        $hosts = json_decode($json, true);

        $query = array();
        $count = 1;
        if (isset($hosts['CommandResponse']['DomainDNSGetHostsResult']['host']) && count($hosts['CommandResponse']['DomainDNSGetHostsResult']['host'])) {
            foreach ($hosts['CommandResponse']['DomainDNSGetHostsResult']['host'] as $key => $data) {
                if ($data['@attributes']['Type'] != 'A' && $data['@attributes']['Type'] != 'NS') {
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

        $query[$key]["HostName$count"] = '@';
        $query[$key]["RecordType$count"] = 'A';
        $query[$key]["Address$count"] = $server_ip;
        $query[$key]["MXPref$count"] = 10;

        $count++;
        $query[$key]["HostName$count"] = 'ns';
        $query[$key]["RecordType$count"] = 'NS';
        $query[$key]["Address$count"] = 'ian.ns.cloudflare.com';
        $query[$key]["MXPref$count"] = 3600;

        $count++;
        $query[$key]["HostName$count"] = 'ns';
        $query[$key]["RecordType$count"] = 'NS';
        $query[$key]["Address$count"] = 'isla.ns.cloudflare.com';
        $query[$key]["MXPref$count"] = 3600;

        Log::channel('domain_process')->info(json_encode($query));

        $string = '';
        foreach ($query as $query_string) {
            $string .= http_build_query($query_string) . '&';
        }

        $namecheap =  Http::get(env('NAMECHEAP_LIVE') . '&Command=namecheap.domains.dns.setHosts&ClientIp=65.108.3.53&SLD=' . $domain[0] . '&TLD=' . $domain[1] . '&' . $string);

        Log::channel('domain_process')->info($namecheap->body());
        $this->server_error_logs($server_main->id, 'DNS update done', json_encode($namecheap->body()));
        $server_main->logger()->info('Updating DNS Namecheap' . json_encode($namecheap->json()));
        return true;
    }
    protected function update_dns_godaddy($domain, $server_ip, $server)
    {

        $server->logger()->info('Updating DNS Godaddy');
        $records = [
            [
                'type' => 'A',
                'ttl' => 600,
                'name' => '@',
                'data' => $server_ip,
            ]
        ];

        $this->server_error_logs($server->id, 'Godaddy DNS update started');
        $dns =  Http::withHeaders([
            'Authorization' => " sso-key " . env('GODADDY_KEY') . ":" . env('GODADDY_SECRET'),
            'content-type' => 'application/json'
        ])->put(env('GODADDY_URL') . "domains/$domain/records/A/@", $records);

        $records = [
            [
                'type' => 'NS',
                'ttl' => 3600,
                'name' => '@',
                'data' => 'ian.ns.cloudflare.com',
            ],
            [
                'type' => 'NS',
                'ttl' => 3600,
                'name' => '@',
                'data' => 'isla.ns.cloudflare.com',
            ],
        ];


        $dns =  Http::withHeaders([
            'Authorization' => " sso-key " . env('GODADDY_KEY') . ":" . env('GODADDY_SECRET'),
            'content-type' => 'application/json'
        ])->put(env('GODADDY_URL') . "domains/$domain/records/NS/$domain", $records);

        $server->logger()->info('Godaddy DNS' . json_encode($dns->json()));
        if ($dns->status() == 200) {
            $server->logger()->info('Godaddy DNS Updated');
            $this->server_error_logs($server->id, 'Godaddy DNS update done', json_encode($dns->json()));
            return true;
        } else {
            $server->logger()->info('Error updating Godaddy DNS' . json_encode($dns->json()));
            $error = new ConnectionLog();
            $error->host = $server->host;
            $error->error = $dns->json();
            $error->error_type = '3';
            $error->sending_server = $server->id;
            $error->save();
            $this->server_error_logs($server->id, 'Godaddy DNS update error', json_encode($dns->json()));
            return false;
        }
    }

    protected function create_ee_proxy($domain, $data, $server_ip, $domain_email, $server)
    {

        $proxy = Proxies::where('status', 1)->inRandomOrder()->first(); //->where('use_count','<=',5)
        $proxy->use_count = $proxy->use_count + 1;
        $proxy->save();
        $proxy_ip = $proxy->ip_address;
        $port = $proxy->port;

        $account = [
            "account" => $domain . '_' . date('his'),
            'email' => $domain_email,
            "name" => $domain . '_' . date('his'),
            'path' => "*",
            'imap' => [
                'auth' => [
                    'user' => $data->imap_username,
                    'pass' => $data->imap_password,
                ],
                "host" => $data->imap_host,
                "port" => $data->imap_port,
                "secure" => true,
                "resyncDelay" => 900
            ],
            'smtp' => [
                'auth' => [
                    'user' => $data->smtp_username,
                    'pass' => $data->smtp_password,
                ],
                "host" => $data->host,
                "port" => $data->smtp_port,
                // "secure" => true,
            ],
            'proxy' => "socks://$proxy_ip:$port"
        ];

        if ($data->smtp_protocol) {
            $account['smtp']['secure'] = true;
        }
        $this->server_error_logs($server->id, 'Creating Eproxy');
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('EE_AUTH'),
            'content-type' => 'application/json'
        ])->post(env('EE_BASE') . "/account", $account);

        $server->logger()->info('Creating EE Proxy' . json_encode($response->json()));
        $this->server_error_logs($server->id, 'Creating Eproxy done', json_encode($response->json()));
        Log::channel('domain_process')->info(json_encode($response->json()));
        $res = $response->json();
        if (isset($res['state']) && $res['state'] == 'new')
            return  $account['account'];
        else
            // $this->create_ee_proxy($domain, $data, $server_ip, $domain_email, $server);
            return false;
    }


    protected function create_tracking_domains($domain, $customer_id, $server_id, $server_host)
    {

        $TrackingDomain = TrackingDomain::where([
            'customer_id' => $customer_id,
            'name' => $domain
        ])
            ->first();
        $this->server_error_logs($server_id, 'Creating Tracking Domain');
        if (!isset($TrackingDomain->id)) {
            // automatically add tracking domain
            $TrackingDomain = new TrackingDomain();
            $TrackingDomain->scheme = 'https';
            $TrackingDomain->name = $domain;
            $TrackingDomain->customer_id =  $customer_id;
            $TrackingDomain->status = 'unverified';
            $TrackingDomain->verification_method = 'host';
            $TrackingDomain->save();
        }

        $url = $domain;
        $verifyUrl = "https://$url/?q=ok";
        Log::channel('domain_process')->info($verifyUrl);
        try {
            $result = file_get_contents($verifyUrl);
            $this->info('tracking:-' . $result);
            Log::channel('doserver')->info("Tracking domain result:- " . $result);
            if ($result == 'ok') {
                Log::channel('doserver')->info("Tracking domains ok:- " . $TrackingDomain->name);
                $TrackingDomain->setVerified();
                $TrackingDomain->save();
                $this->server_error_logs($server_id, 'Creating Tracking Domain done', $TrackingDomain->name);
                return true;
                // $user->domain_created_attached = 1;
                // $user->save();
            } else {
                return false;
            }
        } catch (\Throwable $th) {
            Log::channel('domain_process')->info('acj');
            Log::channel('domain_process')->info($th->getMessage());
            $error = new ConnectionLog();
            $error->host = $server_host;
            $error->error = $th->getMessage();
            $error->error_type = '3';
            $error->sending_server = $server_id;
            $error->save();
            $this->server_error_logs($server_id, 'Creating Tracking Domain error', $th->getMessage());
            return false;
        }
    }


    protected function has_ssl($domain, $servers)
    {
        $res = false;
        $stream = @stream_context_create(array('ssl' => array('capture_peer_cert' => true)));
        $socket = @stream_socket_client('ssl://' . $domain . ':443', $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $stream);

        // If we got a ssl certificate we check here, if the certificate domain
        // matches the website domain.
        if ($socket) {
            $cont = stream_context_get_params($socket);
            $cert_ressource = $cont['options']['ssl']['peer_certificate'];
            $cert = openssl_x509_parse($cert_ressource);

            // Expected name has format "/CN=*.yourdomain.com"
            $namepart = explode('=', $cert['name']);
            $servers->logger()->info('SSL Check namepart ' . json_encode($namepart));
            $this->server_error_logs($servers->id, 'SSL Check namepart', json_encode($namepart));
            // We want to correctly confirm the certificate even 
            // for subdomains like "www.yourdomain.com"
            if (count($namepart) == 2) {
                $cert_domain = trim($namepart[1], '*. ');
                $check_domain = substr($domain, -strlen($cert_domain));
                $servers->logger()->info('SSL Check cert_domain ' . json_encode($cert_domain));
                $servers->logger()->info('SSL Check check_domain ' . json_encode($check_domain));
                $this->server_error_logs($servers->id, 'SSL Check check_domain', json_encode($check_domain));
                $res = ($cert_domain == $check_domain);
            }
        }

        return $res;
    }


    protected function register_domain_cloudfare($domain, $server)
    {
        $records['name'] = $domain;
        $records['type'] = 'full';
        $this->server_error_logs($server->id, 'CloudFare Registration started');
        $request =  Http::withHeaders([
            'Authorization' => " Bearer uDIyZAMuxaRZ_OfkudVwcfgZekvOOGMaUXl8T29r", //hE1uRtUx1E3c0RaE7W_63Xlqq4v1_U3aXOcobTzQ",
            'content-type' => 'application/json'
        ])->post("https://api.cloudflare.com/client/v4/zones", $records);

        $server->logger()->info('Cloudfare api status:- ' . json_encode($request->json()));

        if ($request->status() == 200) {
            $server->logger()->info('Cloudfare Website resgitered');
            $result = $request->json();
            $server->cloudfare_id = $result['result']['id'];
            $server->save();
            $this->server_error_logs($server->id, 'CloudFare resgitered', json_encode($request->json()));
            return $request->json();
        } else {
            $server->logger()->info('Error updating Cloudfare' . json_encode($request->json()));
            $error = new ConnectionLog();
            $error->host = $server->host;
            $error->error = json_encode($request->json());
            $error->error_type = '3';
            $error->sending_server = $server->id;
            $error->save();
            $this->server_error_logs($server->id, 'Error In CloudFare registration', json_encode($request->json()));
            return false;
        }
    }
    protected function server_error_logs($id, $title, $response = '')
    {
        $log = new ServerLog();
        $log->sending_server_id = $id;
        $log->title = $title;
        $log->response = isset($response) ? $response : NULL;
        $log->save();
    }
    public function cloudfare_dns_update($server,$server_ip,$domain,$cloudfare_id)
    {
        $this->info('Cloudfare dn update started ');
        $response = Http::withHeaders([
            "Content-Type: application/json",
            'Authorization' => " Bearer uDIyZAMuxaRZ_OfkudVwcfgZekvOOGMaUXl8T29r", //hE1uRtUx1E3c0RaE7W_63Xlqq4v1_U3aXOcobTzQ",
        ])
        ->put("https://api.cloudflare.com/client/v4/zones/".$cloudfare_id."/dns_records/identifier", [
            'content' => $server_ip,
            'name' => $domain,
            'proxied' => false,
            // 'type' => 'A',
            // 'comment' => 'Domain verification record',
            // 'tags' => ['owner:dns-team'],
            // 'ttl' => 3600,
        ]);
        
        if ($response->failed()) {
            $this->server_error_logs($server->id, 'CloudFare DNS update Error', json_encode($response->json()));
        } else {
            $this->server_error_logs($server->id, 'CloudFare DNS updated');
            return $response->body();
        }
    }
}
