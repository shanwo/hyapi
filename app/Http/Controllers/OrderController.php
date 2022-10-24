<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->orderService = new OrderService();
    }

    public function lists()
    {
        $this->validate([
            'page'     => 'required|integer|gt:0',
        ], [
            'page'     => '页码',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        $data = $this->orderService->lists($this->request,$this->request->user()->id);
        $this->code = 200;
        return $this->returnApi($data);
    }

    public function info()
    {
        $this->validate([
            'order_sn' => 'required|alpha_num|size:16',
        ], [
            'order_sn' => '订单编号',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        $data = $this->orderService->info($this->request);
        $this->code = 200;
        return $this->returnApi($data);
    }
}