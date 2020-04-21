<?php

namespace App\Jobs;

use App\Models\Download;
use App\Models\Video;
use App\User;
use GuzzleHttp\RequestOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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
        $payload = $this->download->payload;

        $path = $payload['mediakey'];

        $guzzle = new Client();

        $api_token = User::where('id', '=', $this->download->user_id)->pluck('api_token')->first();

        $response = $guzzle->post($payload['source']['url'], [
            RequestOptions::JSON => [
                'api_token' => $api_token,
            ]
        ]);

        Storage::disk('uploaded')->put($path, $response->getBody());

        $this->download->update(['processed' => true]);

        $filename = basename($payload['source']['url']);

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
                    'title' => $filename,
                    'target' => $payload,
                ]);
                CreateThumbnailJob::dispatch($thumbnail_item)->onQUeue('video');
            }
        }

        if (isset($payload['spritemap'])) {
            $spritemap = Video::create([
                'user_id' => $this->download->user_id,
                'download_id' => $this->download->id,
                'disk' => 'uploaded',
                'mediakey' => $payload['mediakey'],
                'path' => $path,
                'title' => $filename,
                'target' => $payload
            ]);

            CreateSpritemapJob::dispatch($spritemap)->onQUeue('video');
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
                    'title' => $filename,
                    'target' => $target
                ]);

                ConvertPreviewVideoJob::dispatch($video)->onQueue('video');
            }

            if (isset($payload['target']['hls'])) {
                $video = Video::create([
                    'user_id' => $this->download->user_id,
                    'download_id' => $this->download->id,
                    'disk' => 'uploaded',
                    'mediakey' => $payload['mediakey'],
                    'path' => $path,
                    'title' => $filename,
                    'target' => $target
                ]);

                ConvertHLSVideoJob::dispatch($video)->onQueue('video');
            }

            $video = Video::create([
                'user_id' => $this->download->user_id,
                'download_id' => $this->download->id,
                'disk' => 'uploaded',
                'mediakey' => $payload['mediakey'],
                'path' => $path,
                'title' => $filename,
                'target' => $target
            ]);

            ConvertVideoJob::dispatch($video)->onQueue('video');
        }
    }

    public function failed(\Exception $exception)
    {
        $this->delete();
        echo $exception->getMessage();
    }
}
