<?php

namespace App\Services;

use App\Models\OrderModel;
use EasyWeChat\Pay\Application;

class PayService extends Service
{
    protected $payment;
    protected $orderModel;

    public function __construct()
    {
        parent::__construct();
        $config = $this->redis::GET('config:partner');
        if ($config) {
            $config = json_decode($config, true);
            $config['private_key'] = env('PRIVATE_KEY');
            $config['certificate'] = env('CERTIFICATE');
            $this->payment = new Application($config);
        }
        $this->orderModel = new OrderModel();
    }

    public function send($request)
    {
        $ids = $request->input('ids');
        $vid = $request->input('venue_id');
        $pay = $this->redis::HGET('venues_pay', $vid);
        $pay = json_decode($pay, true);
        $uid = $request->user()->id;
        $order_sn = dechex(time()) . rand(10, 99) . mb_substr(sprintf("%04d", $uid), -4) . rand(10, 99);
        //https://pay.weixin.qq.com/wiki/doc/apiv3_partner/terms_definition/chapter1_1.shtml
        $body = [
            'sp_appid'     => $pay['sp_appid'],
            'sp_mchid'     => $pay['sp_mchid'],
            //'sub_appid'    => $pay['sub_appid'],                    //需要是sub_openid所属的应用
            'sub_mchid'    => $pay['sub_mchid'],
            'description'  => $pay['description'] . ' - 照片购买',
            'out_trade_no' => $order_sn,
            'notify_url'   => $this->payment->getConfig()->get('notify_url'),
            'amount'       => [
                'total' => bcmul(count($ids), $pay['price'], 2)
            ],
            'payer'        => [
                'sp_openid' => $third['wxapp']['open_id'] ?? '',   //用户在服务商appid下的唯一标识。下单前需获取到用户的Openid
                //'sub_openid' => $third['wxapp']['open_id'] ?? ''    //用户在子商户appid下的唯一标识。若传sub_openid，那sub_appid必填。下单前需获取到用户的Openid
            ]
        ];
        try {
            $data = $this->payment->getClient()->postJson('v3/pay/partner/transactions/jsapi', $body)->toArray();
            $data['order_sn'] = $order_sn;
            $insert = [
                'venue_id'     => $vid,
                'user_id'      => $uid,
                'order_sn'     => $body['out_trade_no'],
                'original_ids' => json_encode($ids),
                'price'        => $pay['price'],
                'pay_price'    => $body['amount']['total'],
                'status'       => self::ORDER_CREATE
            ];
            $data['order_id'] = $this->orderModel->insertGetId($insert);
            return $data;
        } catch (\Exception $error) {
            return ['msg' => $error->getMessage()];
        }
    }

    public function result($order_sn)
    {
        try {
            $order = $this->orderModel->where(['order_sn' => $order_sn])->first();
            $pay = $this->redis::HGET('venues_pay', $order['venue_id']);
            $pay = json_decode($pay, true);
            return $this->payment->getClient()->get('v3/pay/partner/transactions/out-trade-no/' . $order_sn, [
                'query' => [
                    'sp_mchid'  => $pay['sp_mchid'],
                    'sub_mchid' => $pay['sub_mchid'],
                ]
            ])->toArray();
        } catch (\Exception $error) {
            return ['msg' => $error->getMessage()];
        }
    }

    public function payment($request)
    {
        $this->redis::SETEX('callback:payment:' . LARAVEL_START . ':request', $this->oneDay / 2, json_encode($request->all()));
        try {
            return $this->payment->getServer()->handlePaid(function ($message, $fail) {
                $data = $this->payment->getClient()->get('v3/pay/partner/transactions/out-trade-no/' . $message['out_trade_no'], [
                    'query' => [
                        'sp_mchid'  => $message['sp_mchid'],
                        'sub_mchid' => $message['sub_mchid']
                    ]
                ])->toArray();
                $this->redis::SETEX('callback:payment:' . LARAVEL_START . ':data', $this->oneDay / 2, json_encode($data));
                if ($data['trade_state'] == 'SUCCESS') {
                    $info = $this->orderModel->where(['order_sn' => $message['out_trade_no']])->first();
                    if ($info && $info['status'] == OrderService::ORDER_CREATE) {
                        $this->orderModel->whereKey($info['id'])->update([
                            'third_sn'   => $data['transaction_id'],
                            'status'     => OrderService::ORDER_SUCCESS,
                            'payated_at' => date('Y-m-d H:i:s', strtotime($data['success_time'])),
                            'snapshot'   => json_encode($message, JSON_UNESCAPED_UNICODE),
                        ]);
                    } else {
                        $this->payment->getClient()->postJson('v3/refund/domestic/refunds', [
                            'sub_mchid'      => $data['sub_mchid'],
                            'transaction_id' => $data['transaction_id'],     //商户订单号
                            'out_refund_no'  => $data['out_trade_no'],       //商户退款单号
                            'reason'         => '订单支付超时',                //退款原因
                            'notify_url'     => env('APP_URL') . 'hy/pay/refund',
                            'amount'         => [
                                'refund'   => $data['amount']['payer_total'],//退款金额
                                'total'    => $data['amount']['total'],      //原订单金额
                                'currency' => 'CNY'
                            ]
                        ])->toArray();
                        $this->payment->getClient()->postJson('v3/pay/partner/transactions/out-trade-no/' . $data['out_trade_no'] . '/close', [
                            'sp_mchid' => $message['sp_mchid'], 'sub_mchid' => $message['sub_mchid']
                        ])->toArray();
                    }
                }
                return true;
            })->serve();
        } catch (\ErrorException $error) {
            $this->redis::SETEX('callback:payment:' . LARAVEL_START . ':error', $this->oneDay / 2, $error->getMessage());
            return false;
        }
    }

    public function refund($request)
    {
        $this->redis::SETEX('callback:refund:' . LARAVEL_START . ':request', $this->oneDay / 2, json_encode($request->all()));
        try {
            return $this->payment->getServer()->handleRefunded(function ($message) {
                $info = $this->orderModel->where(['order_sn' => $message['out_trade_no']])->first();
                $this->orderModel->whereKey($info['id'])->update(['status' => OrderService::ORDER_REFUND]);
                return true;
            });
        } catch (\ErrorException $error) {
            $this->redis::SETEX('callback:refund:' . LARAVEL_START . ':error', $this->oneDay / 2, $error->getMessage());
            return false;
        }
    }

}