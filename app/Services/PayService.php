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
        $config = $this->configInfo('payment');
        $config['private_key'] = env('PRIVATE_KEY');
        $config['certificate'] = env('CERTIFICATE');
        $this->payment = new Application($config);
        $this->orderService = new OrderService();
    }

    public function send($id, $user)
    {
        $order_sn = dechex(time()) . rand(10, 99) . mb_substr(sprintf("%04d", $user->id), -4) . rand(10, 99);
        if ($info['activity_price']) $total_fee = $info['activity_price'];
        $url = 'v3/pay/partner/transactions/jsapi';
        $body = [
            'appid'        => $this->payment->getConfig()->get('app_id'),
            'mchid'        => $this->payment->getConfig()->get('mch_id'),
            'description'  => '滑呀 - 照片购买',
            'out_trade_no' => $order_sn,
            'notify_url'   => $this->payment->getConfig()->get('notify_url'),
            'amount'       => [
                'total'    => $total_fee,
                'currency' => 'CNY'
            ],
            'payer'         =>[
                'openid' => $third['official']['open_id'] ?? ''
            ]
        ];
        try {
            $data = $this->payment->getClient()->postJson($url, $body)->toArray();
            $data['order_sn'] = $order_sn;
            $attribute = $this->redis::GET('user_attribute:' . $user->id);
            $attribute = json_decode($attribute, true);
            $this->redis::SETEX('pay_order:' . $order_sn, $this->oneDay / 24, json_encode(['pay' => $info, 'user' => $attribute], JSON_UNESCAPED_UNICODE));
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
                $pay = json_decode($pay, true);
                $user = (new UserAttributeService())->info($res['user_id']);
                if ($user['type'] != OrderService::USER_VIP) {
                    return $fail('非会员状态不可发起退款');
                }
                $expired = strtotime($user['expired_at']);
                $endTime = time() + $this->lengthTime($pay['type']) - 60;
                if ($expired < $endTime) {
                    return $fail('会员到期时间小于购买时间，不可发起退款');
                }
                $expired = $expired - $this->lengthTime($pay['type']);
                $this->orderService->edit($expired, $user);
                $this->orderService->status($res['id'], OrderService::ORDER_REFUND);
                return true;
            });
        } catch (\ErrorException $error) {
            $this->redis::SETEX('callback:refund:' . LARAVEL_START . ':error', $this->oneDay / 2, $error->getMessage());
            return false;
        }
    }

}