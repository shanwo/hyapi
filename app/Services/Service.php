<?php
/**
 * User: Powerless
 * Date: 2021/6/20
 * Blog: https://wzxaini9.cn
 */

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class Service
{
    const ORDER_CREATE    = 10;
    const ORDER_CANCEL    = 20;
    const ORDER_SUCCESS   = 30;
    const ORDER_FAIL      = 40;
    const ORDER_REFUND    = 50;
    const DELETE_YES      = -1;
    const DELETE_NO       = 1;
    const ENABLE          = 1;
    const DISABLE         = -1;
    public $redis;
    public $page = 1;
    public $size = 10;
    public $oneDay = 86400;

    public function __construct()
    {
        $this->redis = new Redis();
    }
}
