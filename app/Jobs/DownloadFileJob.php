<?php

namespace App\Jobs;

use App\Models\Download;
use App\Models\DownloadJob;
use App\Models\Video;
use App\User;
use GuzzleHttp\RequestOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Queue\Queue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;

class DownloadFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $download;

    public function __construct(Download $download)
    {
        $this->download = $download;
    }

    public function handle()
    {
        Log::info("Entering " . __METHOD__);
        $payload = $this->download->payload;

        $path = $payload['mediakey'];


        $api_token = User::where('id', '=', $this->download->user_id)->pluck('api_token')->first();

        Log::info("Starting download of mediakey" . $payload['mediakey']);

        if ($this->download->processing !== Download::PROCESSING) {
            try {

                $guzzle = new Client();

                Log::info("Processing download of " . $payload['mediakey']);

                $this->download->update(['processed' => Download::PROCESSING]);
                $response = $guzzle->post($payload['source']['url'], [
                    RequestOptions::JSON => [
                        'api_token' => $api_token,
                    ]
                ]);
                Storage::disk('uploaded')->put($path, $response->getBody());
            } catch (\Exception $exception) {
                Log::info('Exception occurred while downloading: ' . $exception->getMessage());
            }
        } else {
            Log::info($path . ' exists already, cancelling');
            $this->delete();
            return;
        }

        Log::info("Finished download of " . $payload['mediakey']);

        if (isset($payload['thumbnail'])) {
            foreach ($payload['thumbnail'] as $thumbnail_key => $thumbnail_value) {
                $thumbnail = array();
                $thumbnail[$thumbnail_key] = $thumbnail_value;
                $payload['thumbnail_item'] = $thumbnail;
                $thumbnail_item = Video::create([
                    'user_id' => $this->download->user_id,
                    'download_id' => $this->download->id,
                    'disk' => 'uploaded',
                    'mediakey' => $payload['mediakey'],
                    'path' => $path,
                    'title' => CreateThumbnailJob::class,
                    'target' => $payload,
                ]);
                $thumbnailJobId = CreateThumbnailJob::dispatch($thumbnail_item)->onQUeue('video');
            }
        }

        if (isset($payload['spritemap'])) {
            $spritemap = Video::create([
                'user_id' => $this->download->user_id,
                'download_id' => $this->download->id,
                'disk' => 'uploaded',
                'mediakey' => $payload['mediakey'],
                'path' => $path,
                'title' => CreateSpritemapJob::class,
                'target' => $payload
            ]);

            $spritemapJobId = CreateSpritemapJob::dispatch($spritemap)->onQUeue('video');
        }

        foreach ($payload['target']['format'] as $target) {
            $target['created_at'] = $payload['source']['created_at'];

            if (isset($payload['target']['start']) && isset($payload['target']['duration'])) {
                $video = Video::create([
                    'user_id' => $this->download->user_id,
                    'download_id' => $this->download->id,
                    'disk' => 'uploaded',
                    'mediakey' => $payload['mediakey'],
                    'path' => $path,
                    'title' => ConvertPreviewVideoJob::class,
                    'target' => $target
                ]);

                $previewVideoJob = new ConvertPreviewVideoJob($video);
                $previewVideoJob->onQueue('video');
                $previewVideoJobId = dispatch($previewVideoJob);
            }

            if (isset($payload['target']['hls'])) {
                $video = Video::create([
                    'user_id' => $this->download->user_id,
                    'download_id' => $this->download->id,
                    'disk' => 'uploaded',
                    'mediakey' => $payload['mediakey'],
                    'path' => $path,
                    'title' => ConvertHLSVideoJob::class,
                    'target' => $target
                ]);

                $hlsVideoJob = new ConvertHLSVideoJob($video);
                $hlsVideoJob->onQueue('video');
                $hlsVideoJobId = dispatch($hlsVideoJob);
            }

            $video = Video::create([
                'user_id' => $this->download->user_id,
                'download_id' => $this->download->id,
                'disk' => 'uploaded',
                'mediakey' => $payload['mediakey'],
                'path' => $path,
                'title' => ConvertVideoJob::class,
                'target' => $target
            ]);

            $videoJob = new ConvertVideoJob($video);
            $videoJob->onQueue('video');
            $videoJobId = dispatch($videoJob);
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function failed(\Exception $exception)
    {
        Log::debug("Entering " . __METHOD__);
        $this->delete();
        echo $exception->getMessage();
        Log::debug("Exiting " . __METHOD__);
    }

    public static function killAssociatedJobs($download_id)
    {
        Log::debug("Entering " . __METHOD__);
        $downloadJobs = DownloadJob::where('download_id', '=', $download_id);
        foreach ($downloadJobs as $downloadJob) {
            $job = DB::table('jobs')->where('id', '=', $downloadJob->job_id)->first();
            Log::info('Deleting job ' . $job->id);
            try {
                $downloadJob->delete();
                $job->delete();
            } catch (\Exception $exception) {
                echo $exception->getMessage();
            }
        }
        Log::debug("Exiting " . __METHOD__);
    }
}
