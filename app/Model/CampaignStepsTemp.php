<?php

/**
 * CampaignSteps class.
 *
 * Model class for campaigns related functionalities.
 * This is the center of the application
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

class CampaignStepsTemp extends Model
{

    protected $table = "campaign_steps_temp";

    public function variants()
    {
        return $this->hasMany('Acelle\Model\CampaignStepsVariantTemp','campaign_steps_id');
    }

    public function random_variant()
    {
        return $this->hasMany('Acelle\Model\CampaignStepsVariantTemp','campaign_steps_id')->where('status',1)->inRandomOrder()
            ->first();
    }
}
