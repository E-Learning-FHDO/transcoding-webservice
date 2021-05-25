<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\DownloadJob;
use App\Models\Media;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PragmaRX\Version\Package\Version;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Response;
use Zip;

class MediaController extends Controller
{

    public static function deleteById($id)
    {
        Log::debug("Entering " . __METHOD__);
        $filenames = DB::table('media')->select('file')->whereIn('id', explode(',', $id))->pluck('file')->toArray();
        Storage::disk('converted')->delete($filenames);
        Log::debug("Exiting " . __METHOD__);
    }

    public function getFile($fileName)
    {
        Log::debug("Entering " . __METHOD__);
        $filePath = Storage::disk('converted')->path($fileName);

        Log::info('Plugin tries to download ' . $fileName . ' with user id ' . Auth::guard('api')->user()->id);

        Media::where('file', '=', $fileName)->where('user_id', '=', Auth::guard('api')->user()->id)->firstOrFail();
        $extension = pathinfo(parse_url($filePath, PHP_URL_PATH), PATHINFO_EXTENSION);

        if ($extension === 'zip') {
            if ($this->isS3Filesystem()) {
                return $this->downloadZipFromS3($fileName);
            }
            return $this->downloadZipFromLocal($fileName);
        }

        if ($this->isS3Filesystem()) {
            return $this->downloadFromS3($fileName);
        }
        return $this->downloadFromLocal($fileName);
    }

    public function setDownloadFinished($fileName)
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('Plugin tries to set file ' . $fileName . ' with user id ' . Auth::guard('api')->user()->id . ' to finished state');

        try {
            $media = Media::where('file', '=', $fileName)->firstOrFail();
            $media->update(['downloaded_at' => Carbon::now()]);
            Log::info('Media ' . $media->file . ' was set to finished state');
            Log::debug("Exiting " . __METHOD__);
            return response()->json(['message' => 'ok'])->setStatusCode(200);
        }
        catch (\Exception $exception) {
            Log::info('setDownloadFinished for filename ' . $fileName . ' failed');
            Log::debug("Exiting " . __METHOD__);
            return response()->json('Not found')->setStatusCode(404);
        }
    }

    public static function deleteAllByMediaKey($mediaKey): void
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('Delete all files and DB entries for mediakey ' . $mediaKey);
        if (!empty($mediaKey)) {
            $fileNames = DB::table('media')->select('file')->whereIn('mediakey', explode(',', $mediaKey))->pluck('file')->toArray();

            $deleteMedia = Media::where('mediakey', $mediaKey)->get();
            Cache::lock('media-' . $deleteMedia->first()->download_id)->get(function () use ($deleteMedia) {
                DownloadJob::where('download_id', '=', $deleteMedia->first()->download_id)->delete();
            });

            foreach ($deleteMedia as $media) {
                if (!empty($media->download)) {
                    Cache::lock('media-' . $media->id)->get(function () use ($media) {
                        $media->download->delete();
                    });
                }
                if (isset($media->target['label'], $media->target['extension'])) {
                    $dir = $media->path . '_' . $media->target['label'] . '_' . $media->target['extension'];
                    if (Storage::disk('converted')->exists($dir)) {
                        Storage::disk('converted')->deleteDirectory($dir);
                    }

                    $previewDir = 'preview_' . $media->path . '_' . $media->target['label'] . '_' . $media->target['extension'];
                    if (Storage::disk('converted')->exists($previewDir)) {
                        Storage::disk('converted')->deleteDirectory($previewDir);
                    }
                }
                Cache::lock('media-' . $media->id)->get(function () use ($media) {
                    $media->delete();
                });
            }
            Storage::disk('converted')->delete($fileNames);
            Storage::disk('uploaded')->delete($mediaKey);
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public static function getMediaStatus($mediaKey)
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('Plugin tries to get transcoding status for mediakey ' . $mediaKey);
        try {
            $download = Download::where('mediakey', '=', $mediaKey)->firstOrFail();
            if ($download->media->count() > 0) {
                $media = Media::where('mediakey', '=', $mediaKey)->firstOrFail();
                $total = Media::where('download_id', $media->download_id)->count();
                $processed = Media::where('download_id', $media->download_id)->where('processed', 1)->count();
                Log::info('Transcoding status for mediakey ' . $mediaKey . ': processed ' . $processed . ' of ' . $total);
                Log::debug("Exiting " . __METHOD__);
                return response()->json(round(($processed / $total) * 100, 0))->setStatusCode(200);
            }
            Log::info('Transcoding status for mediakey ' . $mediaKey . ': no media converted yet.');
            Log::debug("Exiting " . __METHOD__);
            return response()->json(0)->setStatusCode(200);
        } catch (\Exception $exception) {
            Log::info('Transcoding status for mediakey ' . $mediaKey . ': not found');
            Log::debug("Exiting " . __METHOD__);
            return response()->json('Not found')->setStatusCode(404);
        }
    }


    public static function getServiceStatus()
    {
        $response = [
            "name" => config('app.name'),
            "version" => (new Version())->format('compact'),
            "status" => app()->isDownForMaintenance() ? "maintenance" : "running"
        ];
        return response()->json($response)->setStatusCode(200);
    }

    public function testUrl(Request $request)
    {
        Log::debug("Entering " . __METHOD__);
        $apiToken = $request->input('api_token', false);
        $url = $request->input('url', false);
        if ($apiToken && $url) {
            $httpClient = new Client();
            $requestOptions = array(
                RequestOptions::JSON => [
                    'api_token' => $apiToken,
                ]);

            try {
                $response = $httpClient->post($url . '/transcoderwebservice/version', $requestOptions);
                $body = json_decode($response->getBody()->getContents());

                Log::debug("Exiting " . __METHOD__);

                return response()->json($body)->setStatusCode($response->getStatusCode());
            } catch (\Throwable $exception) {
                Log::debug("Exiting " . __METHOD__);
                return response()->json(['message' => $exception->getMessage()])->setStatusCode(400);
            }
        }
        Log::debug("Exiting " . __METHOD__);
        return response()->json(['message' => 'Error'])->setStatusCode(404);
    }

    private function downloadFromLocal($fileName)
    {
        $file = Storage::disk('converted')->path($fileName);
        if (Storage::disk('converted')->exists($fileName)) {
            return response()->download($file, null, [], null);
        }
        return response()->json(['message' => 'File not found'])->setStatusCode(404);
    }

    private function downloadFromS3($fileName)
    {
        if (Storage::disk('converted')->exists($fileName)) {

            $url = Storage::disk('converted')->temporaryUrl(
                $fileName, now()->addHour(),
                ['ResponseContentDisposition' => 'attachment; filename ="' . $fileName . '"']
            );
            return response()->stream(function () use ($url) {
                readfile($url);
            },200, [ "Content-Type" => "application/octet-stream",
                "Content-Disposition" => "attachment; filename=\"" .$fileName. "\""
            ]);
        }
        return response()->json(['message' => 'File not found'])->setStatusCode(404);
    }

    private function downloadZipFromS3($fileName)
    {
        $filePath = Storage::disk('converted')->path($fileName);
        $dir = pathinfo(parse_url($filePath, PHP_URL_PATH), PATHINFO_FILENAME);
        $files = Storage::disk('converted')->files($dir);

        if (count($files) > 0) {
            $zipFile = Zip::create($fileName);

            foreach ($files as $file) {
                $archiveFile = pathinfo(parse_url($file, PHP_URL_PATH), PATHINFO_BASENAME);
                $url = Storage::disk('converted')->temporaryUrl(
                    $file, now()->addHour()
                );
                $zipFile->add($url, $archiveFile);
            }

            Log::debug("Exiting " . __METHOD__);
            return $zipFile;
        }
        return response()->json(['message' => 'No files available'])->setStatusCode(404);
    }

    private function downloadZipFromLocal($fileName)
    {
        $filePath = Storage::disk('converted')->path($fileName);
        $dir = pathinfo(parse_url($filePath, PHP_URL_PATH), PATHINFO_FILENAME);
        $files = Storage::disk('converted')->files($dir);

        if (count($files) > 0) {
            $zipFile = Zip::create($fileName);

            foreach ($files as $file) {
                $archiveFile = pathinfo(parse_url($file, PHP_URL_PATH), PATHINFO_BASENAME);
                $zipFile->add($file, $archiveFile);
            }
            return $zipFile;
        }
        return response()->json(['message' => 'No files available'])->setStatusCode(404);
    }

    private function isS3Filesystem()
    {
        return config('filesystems.disks.converted.driver') === 's3';
    }
}
