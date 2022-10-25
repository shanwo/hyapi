<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class OrderInfoModel extends Model
{
    protected $table = 'hy_order_info';
    public $timestamps = false;

    public function user($uid)
    {
        $this->table = sprintf('hy_order_info%u', $uid % 10);
        if (!Schema::hasTable($this->table)) {
            Schema::create($this->table, function (Blueprint $table) {
                $table->collation = 'utf8mb4_general_ci';
                $table->increments('id');
                $table->unsignedInteger('venue_id')->default('0')->comment('场地ID');
                $table->unsignedInteger('user_id')->default('0')->comment('用户ID')->index('idx_user');
                $table->char('order_sn', 16)->default('')->comment('订单号')->unique('un_order');
                $table->unsignedMediumInteger('pay_price')->default('0')->comment('支付金额(分)');
                $table->json('images')->comment('图片信息')->collation('utf8mb4_general_ci');
                $table->timestamp('created_at')->useCurrent()->comment('创建时间');
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新时间');
            });
        }
    }
}