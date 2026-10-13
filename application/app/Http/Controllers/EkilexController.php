<?php

namespace App\Http\Controllers;

use App\Services\EkilexService;

class EkilexController extends Controller
{
    public function datasets()
    {
        return response()->json(['data' => EkilexService::getDatasets()]);
    }
}
