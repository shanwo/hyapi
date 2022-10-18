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
    protected $userAttributeModel;

    public function __construct()
    {
        parent::__construct();
        $this->orderModel = new OrderModel();
        $this->userAttributeModel = new UserAttributeModel();
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
        $info = $this->userAttributeModel->where(['user_id' => $user['id']])->first();
        $expire = time() + $this->lengthTime($pay['type']);
        if ($info['type'] == Service::USER_VIP) {
            $expire = strtotime($info['expired_at']);
            if (time() < $expire) {
                $expire = $expire + $this->lengthTime($pay['type']);
            }
            if ($pay['type'] < $info['vip_type']) {
                $update['vip_type'] = $pay['type'];
                if (in_array($pay['type'], [1, 2, 3, 4])) {
                    $pages = (Service::PAGE_MAX - $pay['type']) + Service::PAGE_MIN;
                    $update['pages'] = $pages;
                }
            }
        } else {
            $update['vip_type'] = $pay['type'];
            if (in_array($pay['type'], [1, 2, 3, 4])) {
                $pages = (Service::PAGE_MAX - $pay['type']) + Service::PAGE_MIN;
                if ($info['pages'] < $pages) $update['pages'] = $pages;
            }
        }
        $update['type'] = Service::USER_VIP;
        $update['expired_at'] = date('Y-m-d H:i:s', $expire);
        $this->userAttributeModel->where(['user_id' => $user['id']])->update($update);
        $this->setTag('pay_vip', $user['id'], 'Y');
        $this->setTag('vip_type', $user['id'], $pay['type']);
        return $this->redis::SET('user_attribute:' . $user['id'], json_encode(array_merge($user, $update), JSON_UNESCAPED_UNICODE));
    }

    public function edit($expire, $user)
    {
        $update['expired_at'] = date('Y-m-d H:i:s', $expire);
        $this->userAttributeModel->where(['user_id' => $user['id']])->update($update);
        return $this->redis::SET('user_attribute:' . $user['id'], json_encode(array_merge($user, $update), JSON_UNESCAPED_UNICODE));
    }

    public function status($id, $status)
    {
        $update['status'] = $status;
        $this->orderModel->whereKey($id)->update($update);
    }
}
