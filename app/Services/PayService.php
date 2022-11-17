<?php

namespace App\Services;

use App\Models\OrderInfoModel;
use App\Models\OrderModel;
use App\Models\UploadModel;
use EasyWeChat\Pay\Application;
use Qcloud\Cos\Client;

class PayService extends Service
{
    protected $payment;
    protected $orderModel;
    protected $orderInfoModel;

    public function __construct()
    {
        parent::__construct();
        $config = $this->redis::GET('config:partner');
        if (strcmp(app('env'), 'prod')) {
            $config = $this->redis::GET('config:partner-test');
        }
        if ($config) {
            $config = json_decode($config, true);
            $config['private_key'] = env('PRIVATE_KEY');
            $config['certificate'] = env('CERTIFICATE');
            $this->payment = new Application($config);
        }
        $this->orderModel = new OrderModel();
        $this->orderInfoModel = new OrderInfoModel();
    }

    public function send($request)
    {
        $ids = $request->input('ids');
        $ids = array_filter(array_unique($ids));
        $vid = $request->input('venue_id');
        $pay = $this->redis::HGET('venues', $vid);
        $pay = json_decode($pay, true);
        $uid = $request->user()->id;
        $third = array_column($request->user()->third, 'open_id', 'source') ?? [];
        $order_sn = dechex(time()) . rand(10, 99) . mb_substr(sprintf("%04d", $uid), -4) . rand(10, 99);
        $body = [
            'sp_appid'     => $this->payment->getConfig()->get('app_id'),
            'sp_mchid'     => $this->payment->getConfig()->get('mch_id'),
            'sub_mchid'    => $pay['sub_mchid'],
            'description'  => $pay['name'] . ' - 照片购买',
            'out_trade_no' => $order_sn,
            'notify_url'   => $this->payment->getConfig()->get('notify_url'),
            'amount'       => [
                'total' => (int)bcmul(count($ids), $pay['price'])
            ],
            'payer'        => [
                'sp_openid' => $third['wxapp'] ?? '',   //用户在服务商appid下的唯一标识。下单前需获取到用户的Openid
            ]
        ];
        try {
            $prepay = $this->payment->getClient()->postJson('v3/pay/partner/transactions/jsapi', $body)->toArray();
            $data = $this->payment->getUtils()->buildBridgeConfig($prepay['prepay_id'], $body['sp_appid']);
            $data['order_sn'] = $order_sn;
            $insert = [
                'venue_id'     => $vid,
                'user_id'      => $uid,
                'order_sn'     => $order_sn,
                'original_ids' => json_encode($ids),
                'price'        => $pay['price'],
                'pay_price'    => $body['amount']['total'],
                'status'       => self::ORDER_CREATE
            ];
            $this->orderModel->insertGetId($insert);
            return $data;
        } catch (\Exception $error) {
            return ['msg' => $error->getMessage()];
        }
    }

    public function result($order_sn)
    {
        try {
            $order = $this->orderModel->where(['order_sn' => $order_sn])->first();
            $pay = $this->redis::HGET('venues', $order['venue_id']);
            $pay = json_decode($pay, true);
            return $this->payment->getClient()->get('v3/pay/partner/transactions/out-trade-no/' . $order_sn, [
                'query' => [
                    'sp_mchid'  => $this->payment->getConfig()->get('mch_id'),
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
                        'sp_mchid'  => $this->payment->getConfig()->get('mch_id'),
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
                        $images = (new UploadModel())->select('name', 'original_url', 'thumbnail_url')
                            ->whereIn('id', json_decode($info['original_ids'], true))->get()->toArray();
                        $order = [
                            'venue_id'  => $info['venue_id'],
                            'user_id'   => $info['user_id'],
                            'order_sn'  => $message['out_trade_no'],
                            'pay_price' => bcdiv($data['amount']['total'], 100, 2),
                            'images'    => json_encode($this->moveImage($images, $info)),
                        ];
                        $this->orderInfoModel->table($info['user_id']);
                        $this->orderInfoModel->insertGetId($order);
                        $this->redis::DEL('order_info:' . $info['user_id']);
                    } else {
                        $this->payment->getClient()->postJson('v3/refund/domestic/refunds', [
                            'sub_mchid'      => $data['sub_mchid'],
                            'transaction_id' => $data['transaction_id'],     //商户订单号
                            'out_refund_no'  => $data['out_trade_no'],       //商户退款单号
                            'reason'         => '订单支付超时',                //退款原因
                            'notify_url'     => env('APP_URL') . '/hy/pay/refund',
                            'amount'         => [
                                'refund'   => $data['amount']['payer_total'],//退款金额
                                'total'    => $data['amount']['total'],      //原订单金额
                                'currency' => 'CNY'
                            ]
                        ])->toArray();
                        $this->payment->getClient()->postJson('v3/pay/partner/transactions/out-trade-no/' . $data['out_trade_no'] . '/close', [
                            'sp_mchid' => $this->payment->getConfig()->get('mch_id'), 'sub_mchid' => $message['sub_mchid']
                        ])->toArray();
                        $this->orderModel->whereKey($info['id'])->update(['status' => OrderService::ORDER_FAIL]);
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

    private function moveImage($images, $order)
    {
        $config = $this->redis::GET('config:cos');
        if (strcmp(app('env'), 'prod')) {
            $config = $this->redis::GET('config:cos-test');
        }
        $config = json_decode($config, true);
        $cos = new Client([
            'region'      => 'ap-beijing',
            'schema'      => 'https',
            'credentials' => [
                'secretId'  => $config['app_id'],
                'secretKey' => $config['secret'],
            ]
        ]);
        $host = env('APP_CDN');
        $res = [];
        foreach ($images as $k => $v) {
            $original = $cos->Copy($config['target_bucket'],
                '/hy/retained/original/' . $order['order_sn'] . '/' . pathinfo($v['original_url'], PATHINFO_BASENAME),
                ['Region' => 'ap-beijing', 'Bucket' => $config['original_bucket'],
                 'Key'    => substr(parse_url($v['original_url'], PHP_URL_PATH), 1)]
            );
            $thumbnail = $cos->Copy($config['target_bucket'],
                '/hy/retained/thumbnail/' . $order['order_sn'] . '/' . pathinfo($v['thumbnail_url'], PATHINFO_BASENAME),
                ['Region' => 'ap-beijing', 'Bucket' => $config['original_bucket'],
                 'Key'    => substr(parse_url($v['thumbnail_url'], PHP_URL_PATH), 1)]
            );
            $res[] = [
                'name'          => $v['name'],
                'original_url'  => $host .'/'. $original['Key'],
                'thumbnail_url' => $host .'/'. $thumbnail['Key'],

            ];
        }
        return $res;
    }

}