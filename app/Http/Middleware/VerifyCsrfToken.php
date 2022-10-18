<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Auth;
use Ramsey\Uuid\Uuid;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'home/*',
        'pay/payment',
        'pay/refund',
        'test/image',
        'test/check',
    ];

    public function handle($request, Closure $next)
    {
        if (!$this->inExceptArray($request)) {
            if (Auth::guest()) {
                $result = [
                    'data' => (object)[],
                    'code' => 300,
                    'msg'  => '当前账户异常，请重新登录',
                    'uuid' => Uuid::uuid6(),
                ];
                return response()->json($result);
            }
        }
        return $next($request);
    }
}
