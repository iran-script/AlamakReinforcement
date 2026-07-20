<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class MbtilesController extends Controller
{
    public function tile($z,$x,$y)
    {
        // تبدیل XYZ به TMS
        $tmsY = (pow(2,$z)-1)-$y;

        $tile = DB::connection('mbtiles')
            ->table('tiles')
            ->where('zoom_level',$z)
            ->where('tile_column',$x)
            ->where('tile_row',$tmsY)
            ->first();

        if(!$tile){
            abort(404);
        }

        return response($tile->tile_data)
            ->header('Content-Type','image/png');
    }
}