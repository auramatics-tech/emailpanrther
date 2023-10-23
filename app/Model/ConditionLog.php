<?php

/**
 * OpenLog class.
 *
 * Model class for open logging
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

class ConditionLog extends Model
{
    protected $fillable = ['campaign_id', 'step_id', 'variant_id', 'runs_at', 'sent', 'subscriber_id'];

    protected $table = 'condition_logs';
}
