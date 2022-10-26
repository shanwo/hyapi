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

    public function lists($request,$uid)
    {
        $this->orderInfoModel->table($uid);
        $page = $request->input('page', $this->page);
        $where[] = ['user_id', '=', $uid];
        $lists = $this->orderInfoModel->select('id', 'order_sn', 'pay_price', 'images')->where($where);
        $data['page'] = $page;
        $data['total'] = $lists->count();
        $data['lists'] = $lists->orderBy('id', 'DESC')->forPage($page, $this->size)->get()->toArray();
        foreach ($data['lists'] as $k => $v){
            $data['lists'][$k]['images'] = json_decode($v['images'],true);
        }
        return $data;
    }

    public function info($request)
    {
        return $this->orderInfoModel->where(['is_delete', '=', Service::DELETE_NO])->first();
    }
}
