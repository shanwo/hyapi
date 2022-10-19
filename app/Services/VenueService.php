<?php

namespace App\Services;

use App\Models\VenueModel;

class VenueService extends Service
{
    protected $venueModel;

    public function __construct()
    {
        parent::__construct();
        $this->venueModel = new VenueModel();
    }

    public function lists()
    {
        $venues = $this->redis::HGETALL('venues');
        if (!$venues) {
            $venues = $this->venueModel->select('id', 'name', 'image', 'merger_name', 'address')->where(['status' => Service::ENABLE])->get()->toArray();
            foreach ($venues as $k => $v) {
                $this->redis::HSET('venues', $v['id'], json_encode($v, JSON_UNESCAPED_UNICODE));
            }
        } else {
            foreach ($venues as $k => $v) {
                $venues[$k] = json_decode($v, true);
            }
        }
        return ['lists' => array_values($venues)];;
    }

    /*
    每天一个有续集合，集合内存照片墙数据，ZRANGE（venue_images:$venueId:$day）
    通过有续集索引的每小时的哈希数组查看照片 HSCAN（venue_images:$venueId:$day,size）
    */
    public function images($request)
    {
        $venueId = $request->input('venue_id');
        $day = $request->input('day');
        $hour = $request->input('hour','');
        if(is_numeric($hour)){
            $venues = $this->redis::HGET("venue_images_$venueId:$day");
        }
        $page = $request->input('page', $this->page);
        $start = ($page - 1) * $this->size;
        $stop = $start + $this->size - 1;
        $ids = $this->redis::ZREVRANGE("venue_images_$venueId:$day", $start, $stop);
        if (!$start && !$ids) $this->setImages();
        $data = [
            'total' => $this->redis::ZCARD('venues_images'),
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

    private function setImages()
    {
        if ($this->redis::GET('set_home')) return;
//        $where[] = ['p.is_open', '=', Service::ENABLE];
//        $where[] = ['ua.type', '=', Service::USER_VIP];
//        $data = $this->pageModel->from('sw_page as p')->select('p.id', 'p.user_id', 'p.follow', 'p.browse', 'p.is_open', 'p.head_data')
//            ->leftJoin('sw_user_attribute as ua', 'p.user_id', '=', 'ua.user_id')
//            ->where($where)->get()->toArray();
//        foreach ($data as $k => &$v) {
//            $tmp = json_decode($v['head_data'], true);
//            $v = array_merge($v, $tmp);
//            unset($v['head_data']);
//            $this->redis::HSET('user_pages:' . $v['user_id'], $v['id'], json_encode($v, JSON_UNESCAPED_UNICODE));
//        }
        $this->redis::SETEX('set_home', 15, date('Y-m-d H:i:s'));
    }
}