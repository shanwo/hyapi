<?php
/**
 * User: Powerless
 * Date: 21/08/09
 * Blog: https://wzxaini9.cn
 */

namespace App\Http\Controllers;

use App\Services\PayService;
use Illuminate\Http\Request;

class PayController extends Controller
{
    protected $payService;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->payService = new PayService();
    }

    public function lists()
    {
        $this->validate([
            'channel' => 'filled|integer|gt:0',
        ], [
            'channel' => '渠道',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        $data['lists'] = $this->payService->lists($this->request);
        $this->code = 200;
        return $this->returnApi($data);
    }

    public function send()
    {
        $this->validate([
            'id'      => 'required|integer|gt:0',
            'type'    => 'required|alpha_num|in:NATIVE,JSAPI',
            'channel' => 'filled|integer|gte:0',
        ], [
            'id'      => 'ID',
            'channel' => '渠道',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        $id = $this->request->input('id');
        $channel = $this->request->input('channel', 0);
        $pay = $this->service->redis::HGET('pay_channel:' . $channel, $id);
        $user = $this->request->user();
        $type = $this->request->input('type');
        $data = $this->payService->send($pay, $user, $type);
        if (!empty($data['msg'])) {
            $this->msg = $data['msg'];
            return $this->returnApi();
        }
        $this->code = 200;
        return $this->returnApi($data);
    }

    public function result()
    {
        $this->validate([
            'order_sn' => 'required|alpha_num|size:16',
        ], [
            'order_sn' => '订单编号',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        $order_sn = $this->request->input('order_sn');
        $data = $this->payService->result($order_sn);
        if (!empty($data['msg'])) {
            $this->msg = $data['msg'];
            return $this->returnApi();
        }
        $this->code = 200;
        return $this->returnApi($data);
    }


    /**
     * 支付回调
     */
    public function payment()
    {
        $this->validate([
            'id'            => 'required|uuid|between:16,64',
            'create_time'   => 'required|date',
            'event_type'    => 'required|string|between:1,32',
            'resource_type' => 'required|alpha_dash|between:1,16',
            'summary'       => 'required|alpha_num|between:1,64',
            'resource'      => 'required|array',
        ], [
            'id'            => '通知ID',
            'create_time'   => '通知创建时间',
            'event_type'    => '通知类型',
            'resource_type' => '通知数据类型',
            'summary'       => '回调摘要',
            'resource'      => '通知数据',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        return $this->payService->payment($this->request);
    }

    /**
     * 退款回调
     */
    public function refund()
    {
        $this->validate([
            'id'            => 'required|uuid|between:16,64',
            'create_time'   => 'required|date',
            'event_type'    => 'required|string|between:1,32',
            'resource_type' => 'required|alpha_dash|between:1,16',
            'summary'       => 'required|alpha_num|between:1,64',
            'resource'      => 'required|array',
        ], [
            'id'            => '通知ID',
            'create_time'   => '通知创建时间',
            'event_type'    => '通知类型',
            'resource_type' => '通知数据类型',
            'summary'       => '回调摘要',
            'resource'      => '通知数据',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        return $this->payService->refund($this->request);
    }
}
