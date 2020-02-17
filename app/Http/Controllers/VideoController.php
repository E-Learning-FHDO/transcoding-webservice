<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertVideoJob;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VideoController extends Controller
{

    public function getFile($filename)
    {
        $file = Storage::disk('converted')->path($filename);

        Log::info('Plugin tries to download ' . $file);
        $uid = DB::table('videos')->where('file','=', $filename)->pluck('uid')->first();

        if($uid != Auth::guard('api')->user()->id)
        {
            return response()->json([
                'message' => 'Unauthorized'
            ])->setStatusCode(403);
        }

        if(file_exists($file))
        {
            return response()->download($file, null, [], null);
        }

        return response()->json([
            'message' => 'File not found'
        ])->setStatusCode(404);

    }
}
