<?php
/**
 * User: Powerless
 * Date: 21/08/17
 * Blog: https://wzxaini9.cn
 */

namespace App\Services;

use App\Models\OrderModel;

class OrderService extends Service
{
    protected $orderModel;

    public function __construct()
    {
        parent::__construct();
        $this->orderModel = new OrderModel();
    }

    public function lists($request,$uid)
    {
        $page = $request->input('page', $this->page);
        $where[] = ['is_delete', '=', Service::DELETE_NO];
        $data = $this->decorationModel->select('id', 'sort_id', 'name', 'image')->where($where)->get()->toArray();

        return [];
    }

    public function info($request)
    {
        return $this->orderModel->where(['is_delete', '=', Service::DELETE_NO])->first();
    }
}
