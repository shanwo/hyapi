<?php

namespace App\Exceptions;

use App\Mail\NotifyMail;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Mail;
use Psr\Log\LogLevel;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $code = 0;
    protected $levels = [
        MethodNotAllowedHttpException::class => LogLevel::WARNING,                         //警告
        NotFoundHttpException::class         => LogLevel::ERROR,                           //错误
        QueryException::class                => LogLevel::CRITICAL,                        //严重
        \RedisException::class               => LogLevel::CRITICAL,                        //严重
        \InvalidArgumentException::class     => LogLevel::CRITICAL,                        //严重
        \ErrorException::class               => LogLevel::ALERT,                           //警报
        \Error::class                        => LogLevel::EMERGENCY,                       //紧急
    ];
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (MethodNotAllowedHttpException $e, $request) {
            $result = [
                'request' => $request->input(),
                'data'    => $e->getMessage(),
                'code'    => 500,
                'msg'     => '请求类型不匹配',
                'uuid'    => Uuid::uuid6(),
            ];
//            $this->sendEmail($result);
            return response()->json($result);
        });
        $this->renderable(function (NotFoundHttpException $e, $request) {
            $result = [
                'request' => $request->input(),
                'data'    => $e->getMessage(),
                'code'    => 600,
                'msg'     => '请求地址未开放',
                'uuid'    => Uuid::uuid6(),
            ];
//            $this->sendEmail($result);
            return response()->json($result);
        });
        $this->renderable(function (QueryException $e, $request) {
            $result = [
                'request' => $request->input(),
                'data'    => $e->getMessage(),
                'code'    => 700,
                'msg'     => '数据库链接错误',
                'uuid'    => Uuid::uuid6(),
            ];
            $this->sendEmail($result);
            return response()->json($result);
        });
        $this->renderable(function (\RedisException $e, $request) {
            $result = [
                'request' => $request->input(),
                'data'    => $e->getMessage(),
                'code'    => 700,
                'msg'     => '数据库链接错误',
                'uuid'    => Uuid::uuid6(),
            ];
            $this->sendEmail($result);
            return response()->json($result);
        });
        $this->renderable(function (\InvalidArgumentException $e, $request) {
            $result = [
                'request' => $request->input(),
                'data'    => $e->getMessage(),
                'code'    => 700,
                'msg'     => '数据库链接错误',
                'uuid'    => Uuid::uuid6(),
            ];
            $this->sendEmail($result);
            return response()->json($result);
        });
        $this->renderable(function (\ErrorException $e, $request) {
            $result = [
                'request' => $request->input(),
                'data'    => $e->getMessage(),
                'code'    => 800,
                'msg'     => '服务端接口异常',
                'uuid'    => Uuid::uuid6(),
            ];
            $this->sendEmail($result);
            return response()->json($result);
        });
        $this->renderable(function (\Error $e, $request) {
            $result = [
                'request' => $request->input(),
                'data'    => $e->getMessage(),
                'code'    => 900,
                'msg'     => '程序错误请重试',
                'uuid'    => Uuid::uuid6(),
            ];
            $this->sendEmail($result);
            return response()->json($result);
        });
    }

    private function sendEmail($result)
    {
        try {
            Mail::to('develop@shanwo.cc')->send(new NotifyMail($result));
            $msg = '邮件发送成功';
        }catch (\Exception $e){
            $msg = '邮件发送失败：'.$e->getMessage();
        }
        return $msg;
    }
}
