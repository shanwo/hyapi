<?php

namespace App\Services;

use App\Models\UploadModel;
use App\Models\VenueModel;

class VenueService extends Service
{
    protected $venueModel;
    protected $uploadModel;

    public function __construct()
    {
        parent::__construct();
        $this->venueModel = new VenueModel();
        $this->uploadModel = new UploadModel();
    }

    public function lists()
    {
        $venues = $this->redis::HGETALL('venues');
        if (!$venues) {
            if ($this->redis::EXISTS('set_venues')) return [];
            $venues = $this->venueModel->select('id', 'name', 'price', 'image', 'sub_mchid', 'merger_name', 'address')->where(['status' => Service::ENABLE])->get()->toArray();
            foreach ($venues as $k => $v) {
                $this->redis::HSET('venues', $v['id'], json_encode($v, JSON_UNESCAPED_UNICODE));
            }
            $this->redis::SETEX('set_venues', 30, date('Y-m-d H:i:s'));
        } else {
            foreach ($venues as $k => $v) {
                $venues[$k] = json_decode($v, true);
            }
        }
        return ['lists' => array_values($venues)];;
    }

    public function day($request)
    {
        $venueId = $request->input('venue_id');
        $page = $request->input('page', $this->page);
        $start = ($page - 1) * $this->size;
        $stop = $start + $this->size - 1;
        $days = $this->redis::LRANGE('venue_days:' . $venueId, $start, $stop);
        $data['lists'] = [];
        $data['page'] = $page;
        $add = true;
        foreach ($days as $day) {
            if (date('m-d') == $day) $add = false;
            $total = $remainder = 0;$value = 1;$image = [];
            $count = $this->redis::LLEN('venue_hours:' . $venueId . ':' . $day);
            if ($count && $count < 9) {
                $remainder = 9 % $count;
                $value = floor(bcdiv(9, $count, 1));
            }
            $hours = $this->redis::LRANGE('venue_hours:' . $venueId . ':' . $day, 0, -1);
            foreach ($hours as $key => $hour) {
                $total += $this->redis::SCARD('venue_images:' . $venueId . ':' . $day . ':' . $hour);
                if ($key >= 9) continue;
                $num = $value;
                if ($remainder && !$key) $num += $remainder;
                $res = $this->redis::SRANDMEMBER('venue_images:' . $venueId . ':' . $day . ':' . $hour, $num);
                foreach ($res as $k => $v) {
                    $image[] = json_decode($v, true);
                }
            }
            $data['lists'][] = [
                'date'   => $day,
                'total'  => $total,
                'images' => $image
            ];
            $data['total'] = $this->redis::LLEN('venue_days:' . $venueId);
        }
        if ($add && !$this->redis::EXISTS('set_images')) {
            $this->setData($venueId);
            $this->delData($venueId);
        }
        return $data;
    }

    public function hour($request)
    {
        $venueId = $request->input('venue_id');
        $day = $request->input('day');
        $hour = $request->input('hour', 0);
        if (!$hour) $hour = $this->redis::LINDEX('venue_hours:' . $venueId . ':' . $day, 0);
        $data['lists'] = [];
        $data['hours'] = $this->redis::LRANGE('venue_hours:' . $venueId . ':' . $day, 0, -1);
        $data['now'] = $hour;
        $res = $this->redis::SRANDMEMBER('venue_images:' . $venueId . ':' . $day . ':' . $hour, $this->size * 2);
        foreach ($res as $k => $v) {
            $data['lists'][] = json_decode($v, true);
        }
        return $data;
    }

    public function exif($request)
    {
        $venueId = $request->input('venue_id');
        $id = $request->input('id');
        $image = $this->uploadModel->where(['venue_id' => $venueId])->whereKey($id)->first();
        return json_decode($image['exif'], true) ?? [];
    }

    private function setData($venueId)
    {
        $images = $this->uploadModel->where(['venue_id' => $venueId])->whereDate('created_at', '=', date('Y-m-d'))->get()->toArray();
        if (!$images) return;
        $days = array_column($images, 'created_at');
        $hours = [];
        array_walk($days, function (&$item) use (&$hours) {
            $md = substr($item, 5, 8);
            [$item, $hours[$item][]] = explode(' ', $md);
        });
        $days = array_unique($days);
        foreach ($days as $day) {
            $this->redis::LPUSH('venue_days:' . $venueId, $day);
            $hour = array_unique($hours[$day]);
            foreach ($hour as $h) {
                $this->redis::LPUSH('venue_hours:' . $venueId . ':' . $day, $h);
                foreach ($images as $k => $v) {
                    if (substr($v['created_at'], 5, 8) == $day . ' ' . $h) {
                        $img = [
                            'id'  => $v['id'],
                            'url' => $v['thumbnail_url']
                        ];
                        $this->redis::SADD('venue_images:' . $venueId . ':' . $day . ':' . $h, json_encode($img));
                    }
                }
            }
        }
        $this->redis::SETEX('set_images', 900, date('Y-m-d H:i:s'));
    }

    private function delData($venueId)
    {
        $del = $this->redis::LRANGE('venue_days:' . $venueId, self::DURATION, -1);
        foreach ($del as $day) {
            $hour = $this->redis::LRANGE('venue_hours:' . $venueId . ':' . $day, 0, -1);
            foreach ($hour as $h) {
                $this->redis::DEL('venue_images:' . $venueId . ':' . $day . ':' . $h);
            }
            $this->redis::DEL('venue_hours:' . $venueId . ':' . $day);
        }
        $this->redis::LTRIM('venue_days:' . $venueId, 0, self::DURATION);
    }
}