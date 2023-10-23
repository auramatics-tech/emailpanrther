<?php

/**
 * SendingServerSmtp class.
 *
 * Abstract class for standard SMTP sending server
 *
 * LICENSE: This product includes software developed at
 * the Acelle Co., Ltd. (http://acellemail.com/).
 *
 * @category   MVC Model
 *
 * @author     N. Pham <n.pham@acellemail.com>
 * @author     L. Pham <l.pham@acellemail.com>
 * @copyright  Acelle Co., Ltd
 * @license    Acelle Co., Ltd
 *
 * @version    1.0
 *
 * @link       http://acellemail.com
 */

namespace Acelle\Model;

use Acelle\Library\Log as MailLog;
use Illuminate\Support\Facades\Http;
use SendGrid\Mail\Content;
use SendGrid\Mail\Attachment;
use Auth;
use Log;
use Acelle\Library\StringHelper;

class SendingServerSmtp extends SendingServer
{
    protected $table = 'sending_servers';

    /**
     * Send the provided message.
     *
     * @return bool
     *
     * @param message
     */
    public function send($message, $params = array(), $msg_id = '')
    {

        MailLog::info('coming in sent every time campaings are Sent!');

        $params['from_email'] = array_keys($message->getFrom())[0];
        $params['fromName'] = (is_null($message->getFrom())) ? null : array_values($message->getFrom())[0];
        $params['to_email'] = array_keys($message->getTo())[0];
        $params['toName'] = (is_null($message->getTo())) ? null : array_values($message->getTo())[0];
        $params['replyToEmail'] = (is_null($message->getReplyTo())) ? $params['fromEmail'] : (isset(array_keys($message->getReplyTo())[0]) ? array_keys($message->getReplyTo())[0] : '');
        $params['subject'] = $message->getSubject();
        // Following RFC 1341, section 7.2
        //     If either text/html or text/plain are to be sent in your email
        //     text/plain needs to be first, followed by text/html, followed by any other content
        // So, use array_shift instead of array_pop
        // Also, sort the parts so that text/plain comes before text/html

        $parts = $message->getChildren();
        usort($parts, function ($a, $b) {
            if ($a->getContentType() == 'text/plain') {
                return -1;
            } elseif ($a->getContentType() == 'text/html') {
                return 0;
            } else {
                return 1;
            }
        });

        // skip attachment part
        $parts = array_map(function ($part) {
            if (method_exists($part, 'getDisposition')) { // only a part of type Swift_Mime_Attachment has this method
                // add later on
                return null;
            } else {
                return new Content($part->getContentType(), $part->getBody());
            }
        }, $parts);
        // remove null element
        $parts = array_filter($parts);
        $params['plain'] = isset($parts[1]) ? $parts[1]->getValue() : $parts[0]->getValue();

        $attachments = array();
        if (count($params)) {
            foreach ($message->getChildren() as $part) {
                if (method_exists($part, 'getDisposition')) {
                    $filename = basename($part->getFilename());
                    $encoded = base64_encode($part->getBody());
                    $attachments[] =
                        [
                            "filename" => $filename,
                            "content" => $encoded,
                            "contentType" => $part->getContentType(),
                            "contentDisposition" => "attachment",
                            "encoding" => "base64"
                        ];
                }
            }
            // $message_id = $msg_id;
            MailLog::info('mess:-' . $msg_id);
            $mail_data = [
                'from' => [
                    'name' => (isset($params['fromName']) && $params['fromName']) ? $params['fromName'] : $params['from_email'],
                    'address' => $params['from_email']
                ],

                'to' => [
                    [
                        'name' => (isset($params['toName']) && $params['toName']) ? $params['toName'] : $params['to_email'],
                        'address' => $params['to_email']
                    ],
                ],
                'subject' => $params['subject'],
                "html" => $params['plain'],
                "attachments" => $attachments,
                "messageId" => "<$msg_id>"
            ];

            // echo "<pre>";
            // print_r($mail_data);

            MailLog::info(json_encode($mail_data));

            $message_data = [
                'to' => [
                    $params['to_email'],
                ],
                'from' => $params['from_email'],
                'sender' => $params['from_email'],
                'subject' => $params['subject'],
                'html_body' => $params['plain'],
            ];
    
            $url = 'https://cohost.email/api/v1/send/message';
            $proxy = 'socks://38.152.13.166:1080';
            $headers = [
                'content-type' => 'application/json',
                'X-Server-API-Key' => 'Er0Drl784bzYRPyqbEr4pjT9'
            ];
    
            $client = new Client();
    
            $response = $client->post($url, [
                'headers' => $headers,
                'proxy' => $proxy,
                'json' => $message_data,
            ]);
            
            MailLog::info(json_encode($response->json()));
            $mail_response = $response->json();
            if (!isset($mail_response['statusCode'])) {
                MailLog::info('Sent!');
                return array(
                    'status' => self::DELIVERY_STATUS_SENT,
                );
            } else if (isset($mail_response['statusCode'])) {
                throw new \Exception($mail_response['message']);
            }
        }
        // } else {

        //     $transport = new \Swift_SmtpTransport($this->host, (int) $this->smtp_port, $this->smtp_protocol);
        //     $transport->setUsername($this->smtp_username);
        //     $transport->setPassword($this->smtp_password);
        //     // in case of: stream_socket_enable_crypto(): SSL operation failed with code 1. OpenSSL Error messages: error:14090086:SSL routines:SSL3_GET_SERVER_CERTIFICATE:certificate verify failed
        //     $transport->setStreamOptions(array('ssl' => array('allow_self_signed' => true, 'verify_peer' => false, 'verify_peer_name' => false)));

        //     // setup bounce handler: specify the Return-Path
        //     if ($this->bounceHandler) {
        //         $message->setReturnPath($this->bounceHandler->username);
        //     }

        //     // Create the Mailer using your created Transport
        //     $mailer = new \Swift_Mailer($transport);

        //     // Actually send
        //     $sent = $mailer->send($message);


        //     if ($sent) {
        //         MailLog::info('Sent!');

        //         return array(
        //             'status' => self::DELIVERY_STATUS_SENT,
        //         );
        //     } else {
        //         throw new \Exception('Unknown SMTP error');
        //     }
        // }
    }

    /**
     * Check the sending server settings, make sure it does work.
     *
     * @return bool
     */
    public function test()
    {
        $email_engine_proxy = isset(json_decode($this->options)->email_engine_proxy) ? json_decode($this->options)->email_engine_proxy : '';

        if ($this->domain_created_attached == 1) {
            $sending_servers = json_decode($this->options)->identities;
            foreach ($sending_servers as $sending_email => $data) {
                $server_ip = $data->server_ip;
                if ($server_ip)
                    break;
            }

            if ($server_ip) {
                $proxy_user = env("PROXY_USER");
                $proxy_password = env("PROXY_PASSWORD");
                $account = [
                    'imap' => [
                        'auth' => [
                            'user' => $this->imap_username,
                            'pass' => $this->imap_password,
                        ],
                        "host" => $this->imap_host,
                        "port" => $this->imap_port,
                        "secure" => true,
                        "resyncDelay" => 900
                    ],
                    'smtp' => [
                        'auth' => [
                            'user' => $this->smtp_username,
                            'pass' => $this->smtp_password,
                        ],
                        "host" => $this->host,
                        "port" => $this->smtp_port,
                        "secure" => false
                    ],
                    'proxy' => "socks5://$proxy_user:$proxy_password@$server_ip:1080"
                ];

                if ($this->smtp_protocol) {
                    $account['smtp']['secure'] = true;
                }

                // $settings['serviceUrl'] = 'https://app.emailpanther.com';
                //     $response = Http::withHeaders([
                //         'Authorization' => 'Bearer ' . env('EE_AUTH'),
                //         'content-type' => 'application/json'
                //     ])->post(env('EE_BASE') . "settings", $settings);
                // echo "<prE>";
                // print_r($account);
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . env('EE_AUTH'),
                    'content-type' => 'application/json'
                ])->post(env('EE_BASE') . "/verifyAccount", $account);

                $connection = $response->json();
                // echo "<pre>";
                // print_r($connection);
                // die;

                if (isset($connection['smtp']['success']) && $connection['smtp']['success'] && $connection['imap']['success']) {
                    return true;
                } elseif (isset($connection['smtp']['error']) && $connection['smtp']['error']) {
                    throw new \ErrorException($connection['smtp']['error']);
                } elseif (isset($connection['imap']['error']) && $connection['imap']['error']) {
                    throw new \ErrorException($connection['imap']['error']);
                }
            } else {
                throw new \ErrorException('Sending Server not configured.');
            }
            // } else {
            //     $transport = new \Swift_SmtpTransport($this->host, (int) $this->smtp_port, $this->smtp_protocol);
            //     $transport->setUsername($this->smtp_username);
            //     $transport->setPassword($this->smtp_password);

            //     // in case of: stream_socket_enable_crypto(): SSL operation failed with code 1. OpenSSL Error messages: error:14090086:SSL routines:SSL3_GET_SERVER_CERTIFICATE:certificate verify failed
            //     $transport->setStreamOptions(array('ssl' => array('allow_self_signed' => true, 'verify_peer' => false, 'verify_peer_name' => false)));

            //     // Create the Mailer using your created Transport
            //     $mailer = new \Swift_Mailer($transport);
            //     $mailer->getTransport()->start();

            //     return true;
            // }
        }else{
            // throw new \ErrorException("Server is in progress.");
        }
    }

    public function allowVerifyingOwnEmailsRemotely()
    {
        return false;
    }

    public function allowVerifyingOwnDomainsRemotely()
    {
        return false;
    }

    public function syncIdentities()
    {
        // just do nothing
    }

    public static function instantiateFromSettings($settings = [])
    {
        $properties = ['host', 'smtp_port',  'smtp_protocol', 'smtp_username', 'smtp_password', 'from_name', 'from_address'];
        $required = ['host', 'smtp_port', 'smtp_username', 'smtp_password', 'from_address'];

        $server = new self();

        // validate
        foreach ($properties as $property) {
            if ((!array_key_exists($property, $settings) || empty($settings[$property])) && in_array($property, $required)) {
                throw new \Exception("Cannot instantiate SMTP mailer, '{$property}' property is missing");
            }

            $server->{$property} = $settings[$property];
        }

        return $server;
    }

    public function setupBeforeSend($fromEmailAddress)
    {
        //
    }

    public function allowOtherSendingDomains()
    {
        return true;
    }


    public function update_ee($data)
    {

        $proxy_user = env("PROXY_USER");
        $proxy_password = env("PROXY_PASSWORD");
        $sending_servers = json_decode($this->options)->identities;
        foreach ($sending_servers as $sending_email => $proxy) {
            $server_ip = $proxy->server_ip;
            $ee_account = $proxy->proxy_account;

            $account = [
                'path' => "*",
                'imap' => [
                    'auth' => [
                        'user' => $data['imap_username'],
                        'pass' => $data['imap_password'],
                    ],
                    "host" => $data['imap_host'],
                    "port" => $data['imap_port'],
                    "secure" => true,
                    "resyncDelay" => 900
                ],
                'smtp' => [
                    'auth' => [
                        'user' => $data['smtp_username'],
                        'pass' => $data['smtp_password'],
                    ],
                    "host" => $data['host'],
                    "port" => $data['smtp_port'],
                    // "secure" => true,
                ],
                'proxy' => "socks5://$proxy_user:$proxy_password@$server_ip:1080"
            ];


            MailLog::info(json_encode($account));
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('EE_AUTH'),
                'content-type' => 'application/json'
            ])->put(env('EE_BASE') . "/account/$ee_account", $account);

            MailLog::info('update:-' . json_encode($response->json()));
        }
    }
}
