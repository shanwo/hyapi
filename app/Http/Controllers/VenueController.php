<?php

namespace App\Http\Controllers;

use App\Services\VenueService;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    protected $venueService;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->venueService = new VenueService();
    }

    public function lists()
    {
        $data = $this->venueService->lists();
        $this->code = 200;
        return $this->returnApi($data);
    }

    public function day()
    {
        $this->validate([
            'venue_id' => 'required|integer|gt:0|exists:hy_venue,id',
            'page'     => 'required|integer|gt:0',
        ], [
            'venue_id' => '场地ID',
            'page'     => '页码',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        $data = $this->venueService->day($this->request);
        if (!empty($data['msg'])) {
            $this->msg = $data['msg'];
            return $this->returnApi();
        }
        $this->code = 200;
        return $this->returnApi($data);
    }

    public function hour()
    {
        $this->validate([
            'venue_id' => 'required|integer|gt:0|exists:hy_venue,id',
            'day'      => 'required|date_format:m-d',
            'hour'     => 'nullable|date_format:H',
        ], [
            'venue_id' => '场地ID',
            'day'      => '日期',
            'hour'     => '时间',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        $data = $this->venueService->hour($this->request);
        if (!empty($data['msg'])) {
            $this->msg = $data['msg'];
            return $this->returnApi();
        }
        $this->code = 200;
        return $this->returnApi($data);
    }

    public function exif()
    {
        $this->validate([
            'venue_id' => 'required|integer|gt:0|exists:hy_venue,id',
            'id'       => 'required|integer|gt:0|exists:hy_upload,id',
        ], [
            'venue_id' => '场地ID',
            'id'       => '照片ID',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        $data = $this->venueService->exif($this->request);
        $this->code = 200;
        return $this->returnApi($data);
    }
}