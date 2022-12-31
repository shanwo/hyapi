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
