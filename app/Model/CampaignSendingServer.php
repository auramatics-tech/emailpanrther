<?php

namespace Acelle\Model;

use Illuminate\Database\Eloquent\Model;
use Acelle\Library\Tool;

class CampaignSendingServer extends Model
{
    public function clearStorage()
    {
        Tool::xdelete($this->getStoragePath());
    }

    public function deleteAndCleanup()
    {
        $this->clearStorage();
        $this->delete();
    }
}
