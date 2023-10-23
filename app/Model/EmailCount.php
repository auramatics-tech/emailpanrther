<?php

/**
 * BounceLog class.
 *
 * Model class for bounce logs
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
use Exception;
use Validator;

class EmailCount extends Model
{
    protected $table = 'emails_count';

    protected $fillable = [
        'campaign_id',
        'date',
        'count',
        'count_open',
        'count_click'
    ];
}
