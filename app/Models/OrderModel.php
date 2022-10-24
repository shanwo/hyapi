<?php
/**
 * User: Powerless
 * Date: 21/08/17
 * Blog: https://wzxaini9.cn
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class OrderModel extends Model
{
    protected $table = 'hy_order';
    public $timestamps = false;

    public function venue($vid = 0)
    {
        if ($vid) {
            $this->table = sprintf('hy_venue%u_order', $vid);
            if (!Schema::hasTable($this->table)) {
                dump($vid);
                Schema::create($this->table, function (Blueprint $table) {
                    $table->collation = 'utf8mb4_general_ci';
                    $table->increments('id');
                    $table->unsignedInteger('venue_id')->default('0')->comment('场地ID');
                    $table->unsignedInteger('user_id')->default('0')->comment('用户ID')->index('idx_user');
                    $table->char('order_sn', 16)->default('')->comment('订单号')->unique('un_order');
                    $table->string('third_sn', 32)->default('')->comment('三方订单号');
                    $table->json('original_ids')->comment('原始照片ID')->collation('utf8mb4_general_ci');
                    $table->unsignedSmallInteger('price')->default('0')->comment('单价(分)');
                    $table->unsignedMediumInteger('pay_price')->default('0')->comment('支付金额(分)');
                    $table->unsignedTinyInteger('status')->default('1')->comment('订单状态1:创建 2:支付成功 3:支付失败4:退款');
                    $table->text('snapshot')->nullable()->comment('原始信息快照');
                    $table->string('remark')->comment('备注');
                    $table->dateTime('payated_at')->comment('支付时间');
                    $table->timestamp('created_at')->useCurrent()->comment('创建时间');
                    $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新时间');
                });
            }
        }
    }
}
