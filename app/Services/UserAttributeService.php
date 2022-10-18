<?php

namespace App\Services;

use App\Models\UserAttributeModel;

class UserAttributeService extends Service
{
    protected $userAttributeModel;

    public function __construct()
    {
        parent::__construct();
        $this->userAttributeModel = new UserAttributeModel();
    }

    public function info($uid)
    {
        $info = $this->redis::GET('user_attribute:' . $uid);
        $info = json_decode($info, true) ?? [];
        if (!$info) {
            $info = $this->userAttributeModel->where(['user_id' => $uid])->first(['user_id as id', 'type', 'vip_type', 'pages', 'expired_at']);
            if (!$info) {
                $id = $this->userAttributeModel->insertGetId(['user_id' => $uid]);
                $info = $this->userAttributeModel->whereKey($id)->first(['user_id as id', 'type', 'vip_type', 'pages', 'expired_at']);
            } else {
                if ($info['type'] == Service::USER_DISABLE) return ['msg' => '当前账户已被禁用，不可登录'];
            }
            $this->redis::SET('user_attribute:' . $uid, json_encode($info, JSON_UNESCAPED_UNICODE));
        }
        return $info;
    }
}
