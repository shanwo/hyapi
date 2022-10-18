<?php
/**
 * User: Powerless
 * Date: 21/07/01
 * Blog: https://wzxaini9.cn
 */

namespace App\Services;

use App\Models\PageModel;

class HomeService extends Service
{
    protected $pageModel;
    protected $pageService;

    public function __construct()
    {
        parent::__construct();
        $this->pageModel = new PageModel();
        $this->pageService = new PageService();
    }

    public function hot($request)
    {
        $page = $request->input('page', $this->page);
        $start = ($page - 1) * $this->size;
        $stop = $start + $this->size - 1;
        $ids = $this->redis::ZREVRANGE('hot', $start, $stop);
        if (!$start && !$ids) $this->setPage();
        $data = [
            'total' => $this->redis::ZCARD('hot'),
            'page'  => $page,
            'size'  => $this->size,
            'lists' => []
        ];
        foreach ($ids as $k => $v) {
            $info = $this->redis::GET('list_pages:' . $v);
            if ($info) $data['lists'][] = json_decode($info, true);
        }
        return $data;
    }

    public function recent($request)
    {
        $page = $request->input('page', $this->page);
        $ids = $this->redis::SRANDMEMBER('recent', $this->size);
        if (!($page - 1) && !$ids) $this->setPage();
        $data = [
            'total' => $this->redis::SCARD('recent'),
            'page'  => $page,
            'size'  => $this->size,
            'lists' => []
        ];
        foreach ($ids as $k => $v) {
            $info = $this->redis::GET('list_pages:' . $v);
            if ($info) $data['lists'][] = json_decode($info, true);
        }
        return $data;
    }

    public function info($request)
    {
        $code = $request->input('code');
        $id = $this->redis::HGET('pages_code', $code);
        $page = $this->redis::GET('info_pages:' . $id);
        $info = json_decode($page, true) ?? [];
        if (!$info) {
            $info = $this->pageService->info($id);
            if (!$info) {
                $this->redis::SETEX('info_pages:' . $id, 60, '{}');
                return ['msg' => '没有找到要访问的页面'];
            }
        }
        if (!$this->redis::GET('click:' . $id . ':' . $request->ip())) {
            $this->redis::ZINCRBY('hot', 1, $id);
            $this->redis::SETEX('click:' . $id . ':' . $request->ip(), 15, date('Y-m-d H:i:s'));
            $user = $request->user();
            if ($user) {
                $this->redis::ZINCRBY('user_browses_click:' . $user->id, 1, $id);
                $this->redis::ZINCRBY('page_browses_click:' . $id, 1, $user->id);
                $this->redis::LREM('user_browses:' . $user->id, 0, $id);
                $this->redis::LREM('page_browses:' . $id, 0, $user->id);
                $this->redis::LPUSH('user_browses:' . $user->id, $id);
                $this->redis::LPUSH('page_browses:' . $id, $user->id);
            }
        }
        return $info;
    }

    private function setPage()
    {
        if ($this->redis::GET('set_home')) return;
        $where[] = ['p.is_open', '=', Service::ENABLE];
        $where[] = ['ua.type', '=', Service::USER_VIP];
        $data = $this->pageModel->from('sw_page as p')->select('p.id', 'p.user_id', 'p.follow', 'p.browse', 'p.is_open', 'p.head_data')
            ->leftJoin('sw_user_attribute as ua', 'p.user_id', '=', 'ua.user_id')
            ->where($where)->get()->toArray();
        foreach ($data as $k => &$v) {
            $tmp = json_decode($v['head_data'], true);
            $v = array_merge($v, $tmp);
            unset($v['head_data']);
            $this->redis::HSET('user_pages:' . $v['user_id'], $v['id'], json_encode($v, JSON_UNESCAPED_UNICODE));
        }
        $this->redis::SETEX('set_home', 15, date('Y-m-d H:i:s'));
    }
}
