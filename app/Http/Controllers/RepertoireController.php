<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\RepertoireService;

class RepertoireController extends Controller
{
    public function show(Service $service, RepertoireService $repertoires) { $repertoire=$repertoires->forService($service); return view('repertoires.show',compact('service','repertoire')); }
}
