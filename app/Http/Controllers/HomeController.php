<?php
/**
 * User: Powerless
 * Date: 21/07/01
 * Blog: https://wzxaini9.cn
 * Tips: 首页模块
 */

namespace App\Http\Controllers;

use App\Services\HomeService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    protected $homeService;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->homeService = new HomeService();
    }

    public function hot()
    {
        $this->validate([
            'page' => 'required|integer|gt:0',
        ], [
            'page' => '页码',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        $data = $this->homeService->hot($this->request);
        $this->code = 200;
        return $this->returnApi($data);
    }

    public function recent()
    {
        $this->validate([
            'page' => 'required|integer|gt:0',
        ], [
            'page' => '页码',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        $data = $this->homeService->recent($this->request);
        $this->code = 200;
        return $this->returnApi($data);
    }

    public function info()
    {
        $this->validate([
            'code' => 'required|string|between:4,16',
        ], [
            'code' => '闪码',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        $data = $this->homeService->info($this->request);
        if (!empty($data['msg'])) {
            $this->msg = $data['msg'];
            return $this->returnApi();
        }
        $this->code = 200;
        return $this->returnApi($data);
    }
}
