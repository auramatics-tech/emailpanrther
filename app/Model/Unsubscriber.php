<?php

/**
 * Unsubscriber class.
 *
 * Model class for Unsubscriber
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

use Illuminate\Database\Eloquent\Model;
use Acelle\Model\MailList;
use Acelle\Model\Setting;
use Acelle\Events\MailListSubscription;
use Acelle\Events\MailListUnsubscription;
use Acelle\Library\StringHelper;
use DB;
use Exception;
use File;
use Acelle\Library\Traits\HasUid;
use Closure;
use Carbon\Carbon;

class Unsubscriber extends Model
{

    public static $rules = [
        'email' => ['required', 'email:rfc,filter'],
    ];


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'campaign_id', 'subscriber_id',
        'email', 'ip_address','message_id'
    ];


    public function unsubscribeLogs()
    {
        return $this->hasMany('Acelle\Model\UnsubscribeLog');
    }

    /**
     * Unsubscribe to the list.
     */
    public function unsubscribe($trackingInfo)
    {
        // Transaction safe
        DB::transaction(function () use ($trackingInfo) {
            // Create log
            $this->unsubscribeLogs()->create($trackingInfo);
        });
    }


    public function getEmail()
    {
        return $this->email;
    }
}
