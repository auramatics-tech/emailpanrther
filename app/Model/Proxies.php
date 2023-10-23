<?php

namespace Acelle\Model;

use Illuminate\Database\Eloquent\Model;
use Acelle\Library\Tool;
use File;


class Proxies extends Model
{
    protected $table =  'proxies';

    protected $filable = ['ip_address', 'port','status'];
}
