<?php
/**
 * User: Powerless
 * Date: 2021/6/3
 * Blog: https://wzxaini9.cn
 */

namespace App\Http\Controllers;

use App\Services\Service;
use GuzzleHttp\Client;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class Controller extends BaseController
{
    use ValidatesRequests;

    protected Client $client;
    protected $request;
    protected Service $service;
    protected int $code = 100;
    protected string $msg = '';
    protected array $levels = [
        100 => 'debug',
        200 => 'info',
        300 => 'notice',
        400 => 'notice',
    ];

    public function __construct($request)
    {
        $this->request = $request;
        $this->client = new Client();
        $this->service = new Service();
    }

    /**
     * 验证器
     * @param array $rules
     * @param array $customAttributes
     * @param array $messages
     */
    public function validate(array $rules, array $customAttributes = [], array $messages = [])
    {
        $this->code = 400;
        $validator = $this->getValidationFactory()->make($this->request->all(), $rules, $messages, $customAttributes);
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            $this->msg = current($errors);
        }
    }

    /**
     * 需要返回的数据
     * @param object $data
     * @return \Illuminate\Http\JsonResponse
     */
    public function returnApi($data = [])
    {
        $result = [
            'request' => $this->request->all(),
            'data'    => (object)$data,
            'code'    => $this->code,
            'msg'     => $this->msg,
            'uuid'    => Uuid::uuid6(),
        ];
        \Illuminate\Support\Facades\DB::disconnect('mysql');
//        $this->service->redis::SETEX('return:' . $result['uuid'], $this->service->oneDay / 2, json_encode($result, JSON_UNESCAPED_UNICODE));
        unset($result['request']);
        return response()->json($result);
    }

    /**
     * 功能：对字符串进行加密处理
     * @param string $str
     * 需要加密的内容.
     * @param string $key
     * 加密字符串
     * @return string
     * 加密后的内容
     */
    public function passportEncrypt(string $str, string $key): string
    {
        $encrypt_key = md5(rand(0, 32000));
        $ctr = 0;
        $tmp = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
            $tmp .= $encrypt_key[$ctr] . ($str[$i] ^ $encrypt_key[$ctr++]);
        }
        return base64_encode($this->passportKey($tmp, $key));
    }

    /**
     * 辅助函数
     * @param $str
     * @param $encrypt_key
     * @return string
     */
    private function passportKey($str, $encrypt_key)
    {
        $encrypt_key = md5($encrypt_key);
        $ctr = 0;
        $tmp = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
            $tmp .= $str[$i] ^ $encrypt_key[$ctr++];
        }
        return $tmp;
    }

    /**
     * 功能：对字符串进行解密处理
     * @param string $str
     * 需要解密的密文.
     * @param string $key
     * 加密字符串
     * @return string
     * 解出的内容
     */
    public function passportDecrypt(string $str, string $key): string
    {
        $str = $this->passportKey(base64_decode($str), $key);
        $tmp = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $md5 = $str[$i];
            $tmp .= $str[++$i] ^ $md5;
        }
        return $tmp;
    }
}
