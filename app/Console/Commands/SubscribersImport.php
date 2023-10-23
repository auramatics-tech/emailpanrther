<?php

namespace Acelle\Console\Commands;

use Illuminate\Console\Command;
use Acelle\Library\Traits\Trackable;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Acelle\Model\MailList;
use Acelle\Model\Subscriber;
use Acelle\Library\MailListFieldMapping;
use Acelle\Model\Field;
use Exception;
use Log;
use DB;

class SubscribersImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sub:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Attach server with domains';

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
        $this->info(date('Y-m-d H:i:s'));
        // Read CSV files
        $file = '/var/www/app.emailpanther.com/storage/app/tmp/import/import-642a8aa6aad18.csv';
        $maillist = MailList::find(76);
        list($headers, $total, $results) = $maillist->readCsv($file);
        $mapArray = [];
        foreach ($maillist->fields as $field) {
            foreach ($headers as $header) {
                if (strtolower($field->tag) == strtolower($header)) {
                    $mapArray[$header] = $field->id;
                }
            }
        }

        $map = MailListFieldMapping::parse($mapArray, $maillist);

        $this->info($total);
        $count = 0;
        if ($total) {
            foreach ($results as $subscriber) {
                $insert_data[$count]['uid'] = uniqid();
                $insert_data[$count]['mail_list_id'] = 76;
                $insert_data[$count]['email'] = $subscriber['Email'];
                $insert_data[$count]['status'] = db_quote(Subscriber::STATUS_SUBSCRIBED);
                $insert_data[$count]['subscription_type'] = db_quote(Subscriber::SUBSCRIPTION_TYPE_IMPORTED);
                $insert_data[$count]['tags'] = '';
                $insert_data[$count]['created_at'] = date('Y-m-d H:i:s');
                $insert_data[$count]['updated_at'] = date('Y-m-d H:i:s');
                $count++;
            }


            $this->info(date('Y-m-d H:i:s'));

            $this->info(date('Y-m-d H:i:s'));
            $maillist = new Subscriber;
            $maillist->fill($insert_data);
            $maillist->save();

            $this->info(date('Y-m-d H:i:s'));
        }
    }
}
