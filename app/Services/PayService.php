<?php

namespace App\Services;

use EasyWeChat\Pay\Application;

class PayService extends Service
{
    protected $payment;
    protected $orderService;

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
        $this->orderService = new OrderService();
    }

    public function send($request)
    {
        $ids = $request->input('id');
        $vid = $request->input('venue_id');
        $uid = $request->user()->id;
        $pay = $this->redis::HGET('venues_pay',$vid);
        $pay = json_decode($pay,true);
        dump($pay);die;
        $order_sn = dechex(time()) . rand(10, 99) . mb_substr(sprintf("%04d", $uid), -4) . rand(10, 99);
        $body = [
            'sp_appid'        => $pay['sp_appid'],
            'sp_mchid'        => $pay['sp_mchid'],
            'sub_appid'        => $pay['sub_appid'],
            'sub_mchid'        => $pay['sub_mchid'],
            'description'  => '滑呀 - 照片购买',
            'out_trade_no' => $order_sn,
            'notify_url'   => $this->payment->getConfig()->get('notify_url'),
            'amount'       => [
                'total'    => bcmul(count($ids),20,2)
            ],
            'payer'         =>[
                'sp_openid' => $third['official']['open_id'] ?? '',
                'sub_openid' => $third['official']['open_id'] ?? ''
            ]
        ];
        try {
            $data = $this->payment->getClient()->postJson('v3/pay/partner/transactions/jsapi', $body)->toArray();
            $data['order_sn'] = $order_sn;
            $this->redis::SETEX('pay_order:' . $order_sn, $this->oneDay / 24, json_encode(['pay' => $info, 'user_id' => $uid], JSON_UNESCAPED_UNICODE));
            return $data;
        } catch (\Exception $error) {
            return ['msg' => $error->getMessage()];
        }
    }

    public function result($order_sn)
    {
        try {
            return $this->payment->getClient()->get('v3/pay/transactions/out-trade-no/' . $order_sn, [
                'query' => ['mchid' => $this->payment->getConfig()->get('mch_id')]
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
                $data = $this->payment->getClient()->get('v3/pay/transactions/out-trade-no/' . $message['out_trade_no'], [
                    'query' => ['mchid' => $this->payment->getConfig()->get('mch_id')]
                ])->toArray();
                $res = $this->orderService->info(['order_sn' => $message['out_trade_no']]);
                $this->redis::SETEX('callback:payment:' . LARAVEL_START . ':data', $this->oneDay / 2, json_encode($data));
                switch ($data['trade_state']) {
                    case 'SUCCESS':     //支付成功
                        if ($res) break;
                        $info = $this->redis::GET('pay_order:' . $data['out_trade_no']);
                        $info = json_decode($info, true) ?? [];
                        if ($info) {
                            $this->orderService->add($data, $info);
                        } else {
                            $this->payment->getClient()->postJson('v3/refund/domestic/refunds', [
                                'transaction_id' => $data['transaction_id'],     //商户订单号
                                'out_refund_no'  => $data['out_trade_no'],       //商户退款单号
                                'reason'         => '订单支付超时',                 //退款原因
                                'notify_url'     => env('APP_URL') . '/pay/refund',
                                'amount'         => [
                                    'refund'   => $data['amount']['payer_total'],//退款金额
                                    'total'    => $data['amount']['total'],      //原订单金额
                                    'currency' => 'CNY'
                                ]
                            ])->toArray();
                            $this->payment->getClient()->postJson('v3/pay/transactions/out-trade-no/' . $data['out_trade_no'] . '/close', [
                                ['mchid' => $this->payment->getConfig()->get('mch_id')]
                            ])->toArray();
                        }
                        break;
                    case 'CLOSED':      //已关闭
                        if ($res && $res['status'] != OrderService::ORDER_SUCCESS) {
                            $this->orderService->status($res['id'], OrderService::ORDER_CANCEL);
                        }
                        break;
                    case 'PAYERROR':    //支付失败（仅付款码支付会返回）
                        if ($res && $res['status'] != OrderService::ORDER_SUCCESS) {
                            $this->orderService->status($res['id'], OrderService::ORDER_FAIL);
                        }
                        break;
                    case 'REFUND':      //转入退款
                        if ($res && $res['status'] != OrderService::ORDER_SUCCESS) {
                            $this->orderService->status($res['id'], OrderService::ORDER_REFUND);
                        }
                        break;
                    case 'NOTPAY':      //未支付
                        break;
                    case 'REVOKED':     //已撤销（仅付款码支付会返回）
                        break;
                    case 'USERPAYING':  //用户支付中（仅付款码支付会返回）
                        break;
                    default:
                        return $fail('支付验证失败,请查看日志:' . time());
                }
                $this->redis::DEL('pay_order:' . $message['out_trade_no']);
                return true;
            })->serve();
        } catch (\ErrorException $error) {
            $this->redis::SETEX('callback:payment:' . LARAVEL_START . ':error', $this->oneDay / 2, $error->getMessage());
            return $error->getMessage();
        }
    }

    public function refund($request)
    {
        $this->redis::SETEX('callback:refund:' . LARAVEL_START . ':request', $this->oneDay / 2, json_encode($request->all()));
        try {
            return $this->payment->getServer()->handleRefunded(function ($message, $reqInfo, $fail) {
                $res = $this->orderService->info(['order_sn' => $message['out_trade_no']]);
                $pay = $this->redis::HGET('pay_channel:' . $res['channel'], $res['pay_id']);
                $this->orderService->status($res['id'], OrderService::ORDER_REFUND);
                return true;
            });
        } catch (\ErrorException $error) {
            $this->redis::SETEX('callback:refund:' . LARAVEL_START . ':error', $this->oneDay / 2, $error->getMessage());
            return false;
        }
    }

}