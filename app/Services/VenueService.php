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
            if ($this->redis::GET('set_venues')) return [];
            $venues = $this->venueModel->select('id', 'name', 'image', 'merger_name', 'address')->where(['status' => Service::ENABLE])->get()->toArray();
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
        $data['page'] = $page;
        foreach ($days as $day) {
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
                foreach ($res as $k => $v){
                    $image[] = json_decode($v,true);
                }
            }
            $data['lists'][] = [
                'date'  => $day,
                'total' => $total,
                'images'=> $image
            ];
            $data['total'] = $this->redis::LLEN('venue_days:' . $venueId);
        }
        return $data;
    }

    public function hour($request)
    {
        $venueId = $request->input('venue_id');
        $day = $request->input('day');
        $hour = $request->input('hour',0);
        if(!$hour){
            $hour = $this->redis::LINDEX('venue_hours:' . $venueId . ':' . $day, 0);
        }
        $data['lists'] = [];
        $data['hours'] = $this->redis::LRANGE('venue_hours:' . $venueId . ':' . $day, 0, -1);
        $data['now'] = $hour;
        $res = $this->redis::SRANDMEMBER('venue_images:' . $venueId . ':' . $day . ':' . $hour, 10);
        foreach ($res as $k => $v){
            $data['lists'][] = json_decode($v,true);
        }
        return $data;
    }
}