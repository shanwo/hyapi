<?php
/**
 * User: Powerless
 * Date: 21/08/11
 * Blog: https://wzxaini9.cn
 */

namespace App\Exceptions;

use Illuminate\Support\Facades\Redis;
use Illuminate\Auth\EloquentUserProvider;

class RedisUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by the given credentials.
     *
     * @param array $credentials
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (!isset($credentials['api_token'])) return ;
        $userId = Redis::GET('user_tokens:' . $credentials['api_token']);
        if ($userId) {
            $user = Redis::GET('user_infos:' . $userId);
            return json_decode($user);
        }
//        if ($userId) return $this->retrieveById($userId);     //从数据库读取
    }
}
