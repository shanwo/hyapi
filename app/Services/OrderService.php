<?php
/**
 * User: Powerless
 * Date: 21/08/17
 * Blog: https://wzxaini9.cn
 */

namespace App\Services;

use App\Models\OrderModel;
use App\Models\UserAttributeModel;

class OrderService extends Service
{
    protected $orderModel;

    public function __construct()
    {
        parent::__construct();
        $this->orderModel = new OrderModel();
    }

    public function info($where)
    {
        return $this->orderModel->where($where)->first();
    }

    public function add($request, $info)
    {
        $user = $info['user'];
        $pay = $info['pay'];
        $insert = [
            'user_id'        => $user['id'],
            'pay_id'         => $pay['id'],
            'channel'        => $pay['channel'],
            'order_sn'       => $request['out_trade_no'],
            'third_sn'       => $request['transaction_id'],
            'pay_type'       => $request['trade_type'],
            'original_price' => $pay['original_price'],
            'activity_price' => $pay['activity_price'],
            'pay_price'      => $request['amount']['payer_total'],
            'status'         => self::ORDER_SUCCESS,
            'payated_at'     => date('Y-m-d H:i:s', strtotime($request['success_time'])),
            'snapshot'       => json_encode($request, JSON_UNESCAPED_UNICODE),
        ];
        $this->orderModel->insertGetId($insert);
        return $this->redis::SET('user_attribute:' . $user['id'], json_encode($user, JSON_UNESCAPED_UNICODE));
    }

    public function status($id, $status)
    {
        $update['status'] = $status;
        $this->orderModel->whereKey($id)->update($update);
    }
}
