<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertVideoJob;
use App\Models\Download;
use App\Models\DownloadJob;
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
        Log::debug("Entering " . __METHOD__);
        $filenames = DB::table('videos')->select('file')->whereIn('id', explode(',', $id))->pluck('file')->toArray();
        Storage::disk('converted')->delete($filenames);
        Log::debug("Exiting " . __METHOD__);
    }

    public function getFile($filename)
    {
        Log::debug("Entering " . __METHOD__);
        $file = Storage::disk('converted')->path($filename);

        Log::info('Plugin tries to download ' . $filename . ' with user id ' . Auth::guard('api')->user()->id);
        //$uid = DB::table('videos')->where('file','=', $filename)->pluck('user_id')->first();

        try {
            Video::where('file', '=', $filename)->where('user_id', '=', Auth::guard('api')->user()->id)->firstOrFail();
            if (file_exists($file)) {
                Log::debug("Exiting " . __METHOD__);
                return response()->download($file, null, [], null);
            }
            Log::debug("Exiting " . __METHOD__);
            return response()->json([
                'message' => 'File not found'
            ])->setStatusCode(404);
        } catch (\Exception $exception) {
            Log::debug("Exiting " . __METHOD__);
            return response()->json(['message' => $exception->getMessage()])->setStatusCode(500);
        }
    }

    public function setDownloadFinished($filename)
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('Plugin tries to set file ' . $filename . ' with user id ' . Auth::guard('api')->user()->id . ' to finished state');

        $video = Video::where('file', '=', $filename);
        $video->update(['downloaded_at' => Carbon::now()]);
        Log::info('Video ' . $video->pluck('file') . ' was set to finished state');
        Log::debug("Exiting " . __METHOD__);
        return response()->json(['message' => 'ok'])->setStatusCode(200);
    }

    public static function deleteAllByMediaKey($mediakey)
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('Delete all files and DB entries for mediakey ' . $mediakey);
        if (!empty($mediakey)) {
            $filenames = DB::table('videos')->select('file')->whereIn('mediakey', explode(',', $mediakey))->pluck('file')->toArray();

            $deleteVideo = Video::where('mediakey', $mediakey)->get();
            DownloadJob::where('download_id', '=', $deleteVideo->first()->download_id)->delete();

            foreach ($deleteVideo as $video) {
                if (!empty($video->download)) {
                    $video->download->delete();
                }
                if(isset($video->target['label'], $video->target['extension'])) {
                    Storage::disk('converted')->deleteDirectory($video->path . '_' . $video->target['label'] . '_' . $video->target['extension']);
                }
                $video->delete();
            }
            Storage::disk('converted')->delete($filenames);
            Storage::disk('uploaded')->delete($mediakey);
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public static function getStatus($mediakey)
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('Plugin tries to get transcoding status for mediakey ' . $mediakey);
        try {
            $download = Download::where('mediakey', '=', $mediakey)->firstOrFail();
            if ($download->videos->count() > 0) {
                $video = Video::where('mediakey', '=', $mediakey)->firstOrFail();
                $total = Video::where('download_id', $video->download_id)->count();
                $processed = Video::where('download_id', $video->download_id)->where('processed', 1)->count();
                Log::info('Transcoding status for mediakey ' . $mediakey . ': processed ' . $processed . ' of ' . $total);
                Log::debug("Exiting " . __METHOD__);
                return response()->json(round(($processed / $total) * 100, 0))->setStatusCode(200);
            }
            Log::info('Transcoding status for mediakey ' . $mediakey . ': no videos converted yet.');
            Log::debug("Exiting " . __METHOD__);
            return response()->json(0)->setStatusCode(200);
        } catch (\Exception $exception) {
            Log::info('Transcoding status for mediakey ' . $mediakey . ': not found');
            Log::debug("Exiting " . __METHOD__);
            return response()->json('Not found')->setStatusCode(404);
        }
    }
}
