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

    public function images()
    {
        $this->validate([
            'venue_id' => 'required|integer|gte:1',
            'day'      => 'required|date_format:m-d',
            'hour'     => 'filled|date_format:H',
            'page'     => 'required|integer|gt:0',
        ], [
            'venue_id' => '场地ID',
            'day'      => '日期',
            'hour'     => '时间',
            'page'     => '页码',
        ]);
        if ($this->msg) {
            return $this->returnApi();
        }
        $data = $this->venueService->images($this->request);
        $this->code = 200;
        return $this->returnApi($data);
    }
}