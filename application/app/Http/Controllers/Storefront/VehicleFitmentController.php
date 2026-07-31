<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\VehicleFitment\VehicleLookup;
use Illuminate\Http\Request;

final class VehicleFitmentController extends Controller
{
    public function create()
    {
        return view('storefront.fitment.form');
    }

    public function store(Request $r, VehicleLookup $lookup)
    {
        $v = $r->validate(['registration' => ['required', 'string', 'max:16']]);
        $fitment = $lookup->lookup($v['registration']);

        return view('storefront.fitment.form', compact('fitment'));
    }
}
