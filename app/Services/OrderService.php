<?php
/**
 * User: Powerless
 * Date: 21/08/17
 * Blog: https://wzxaini9.cn
 */

namespace App\Services;

use App\Models\OrderInfoModel;

class OrderService extends Service
{
    protected $orderInfoModel;

    public function __construct()
    {
        parent::__construct();
        $this->orderInfoModel = new OrderInfoModel();
    }

    public function lists($request, $uid)
    {
        $page = $request->input('page', $this->page);
        $data['lists'] = [];
        $data['page'] = $page;
        $data['total'] = $this->redis::LLEN('order_info:' . $uid);
        if ($data['total']) {
            $start = ($page - 1) * $this->size;
            $stop = $start + $this->size - 1;
            $orders = $this->redis::LRANGE('order_info:' . $uid, $start, $stop);
            foreach ($orders as $k => $v) {
                $data['lists'] = json_decode($v, true);
            }
        } else {
            $this->orderInfoModel->table($uid);
            $lists = $this->orderInfoModel->select('id', 'order_sn', 'pay_price', 'images')->where(['user_id' => $uid])->orderBy('id', 'DESC')->get()->toArray();
            foreach ($lists as $k => $v) {
                $v['images'] = json_decode($v['images'], true);
                $lists[$k] = $v;
                $this->redis::RPUSH('order_info:' . $uid, json_encode($v, JSON_UNESCAPED_UNICODE));
            }
            $data['lists'] = array_slice($lists, 0, 5);
            $data['total'] = count($lists);
        }
        return $data;
    }
}
