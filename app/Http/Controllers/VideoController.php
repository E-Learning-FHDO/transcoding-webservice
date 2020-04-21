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

        Log::info('Plugin tries to download ' . $filename . ' with user id '. Auth::guard('api')->user()->id);
        //$uid = DB::table('videos')->where('file','=', $filename)->pluck('user_id')->first();

        try
        {
            Video::where('file','=', $filename)->where('user_id','=', Auth::guard('api')->user()->id)->firstOrFail();
            if(file_exists($file))
            {
                return response()->download($file, null, [], null);
            }
            return response()->json([
                'message' => 'File not found'
            ])->setStatusCode(404);
        }
        catch (\Exception $exception)
        {
            return response()->json(['message' => $exception->getMessage()])->setStatusCode(500);
        }
    }

    public function setDownloadFinished($filename)
    {
        Log::info('Plugin tries to set ' . $filename . ' with user id '. Auth::guard('api')->user()->id . ' to finished state');
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
            Storage::disk('converted')->deleteDirectory($mediakey);
        }
    }

    public static function getStatus($mediakey)
    {
        Log::info('Plugin tries to get transcoding status for mediakey '. $mediakey);
        try
        {
            $download = Download::where('mediakey','=', $mediakey)->firstOrFail();
            if($download->videos->count() > 0)
            {
                $video = Video::where('mediakey','=', $mediakey)->firstOrFail();
                $total = Video::where('download_id', $video->download_id)->count();
                $processed = Video::where('download_id', $video->download_id)->where('processed', 1)->count();
                return response()->json( round(($processed/$total) * 100, 0))->setStatusCode(200);
            }
            return response()->json( 0)->setStatusCode(200);
        }

        catch(\Exception $exception)
        {
            return response()->json( 'Not found')->setStatusCode(404);
        }
    }
}
