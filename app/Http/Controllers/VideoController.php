<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertVideoJob;
use App\Models\Download;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VideoController extends Controller
{

    public static function deleteById($id)
    {
        $filenames = DB::table('videos')->select('file')->whereIn('id', explode(',', $id))->pluck('file')->toArray();
        Storage::disk('converted')->delete($filenames);
    }

    public function getFile($filename)
    {
        $file = Storage::disk('converted')->path($filename);

        Log::info('Plugin tries to download ' . $file);
        $uid = DB::table('videos')->where('file','=', $filename)->pluck('user_id')->first();

        if($uid != Auth::guard('api')->user()->id)
        {
            return response()->json([
                'message' => 'Unauthorized'
            ])->setStatusCode(403);
        }

        if(file_exists($file))
        {
            if (request()->isMethod('delete')) {
                return response()->json([
                    'message' => 'File is marked as complete and will be deleted'
                ])->setStatusCode(200);
            }
            return response()->download($file, null, [], null);
        }

        return response()->json([
            'message' => 'File not found'
        ])->setStatusCode(404);

    }

    public function setDownloadFinished($filename)
    {
        Log::info('Plugin tries to set ' . $filename . ' to finished state');
        try {
            $video = Video::where('file','=', $filename)->where('user_id','=', Auth::guard('api')->user()->id)->firstOrFail();
            $video->update(['downloaded_at' => Carbon::now()]);
            return response()->json(['message' => 'ok'])->setStatusCode(200);
        }
        catch (\Exception $exception)
        {
            return response()->json([
                'message' => $exception
            ])->setStatusCode(500);
        }
    }

    public static function deleteAllByMediaKey($mediakey)
    {
        Log::info('Delete all files and DB entries for mediakey '. $mediakey);
        if(!empty($mediakey))
        {
            $filenames = DB::table('videos')->select('file')->whereIn('mediakey', explode(',', $mediakey))->pluck('file')->toArray();

            $deleteVideo = Video::where('mediakey', $mediakey)->get();

            foreach($deleteVideo as $video)
            {
                if(!empty($video->download))
                {
                    $video->download->delete();
                }

                $video->delete();
            }
            Storage::disk('converted')->delete($filenames);
            Storage::disk('uploaded')->delete($mediakey);
        }
    }

    public static function getStatus($mediakey)
    {
        try
        {
            $video = Video::where('mediakey','=', $mediakey)->firstOrFail();
            $total = Video::where('download_id', $video->download_id)->count();
            $processed = Video::where('download_id', $video->download_id)->where('processed', 1)->count();
            return response()->json( ($processed/$total) * 100)->setStatusCode(200);
        }

        catch(\Exception $exception)
        {
            return response()->json( 'Not found')->setStatusCode(404);
        }
    }
}
