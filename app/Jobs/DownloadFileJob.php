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
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Illuminate\Http\File;

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

        $path = $payload['source']['mediakey'];

        $guzzle = new Client();

        $api_token =  User::where('id','=', $this->download->uid)->pluck('api_token')->first();

        $response = $guzzle->post($payload['source']['url'], [
            RequestOptions::JSON => [
                'api_token' => $api_token,
            ]
        ]);

        Storage::disk('uploaded')->put($path, $response->getBody());

        $this->download->update(['processed' => true]);

        $filename = basename($payload['source']['url']);

        if(isset($payload['thumbnail']))
        {
            foreach($payload['thumbnail'] as $thumbnail_key => $thumbnail_value)
            {
                $thumbnail = array();
                $thumbnail[$thumbnail_key] = $thumbnail_value;
                $payload['thumbnail_item'] = $thumbnail;
                $thumbnail_item = Video::create([
                    'uid' => $this->download->uid,
                    'disk' => 'uploaded',
                    'mediakey' => $payload['source']['mediakey'],
                    'path' => $path,
                    'title' => $filename,
                    'target' => $payload,
                ]);
                CreateThumbnailJob::dispatch($thumbnail_item)->onQUeue('video');
            }
        }

        foreach($payload['target'] as $target)
        {
            $target['created_at'] = $payload['source']['created_at'];

            $video = Video::create([
                'uid'           => $this->download->uid,
                'disk'          => 'uploaded',
                'mediakey'      => $payload['source']['mediakey'],
                'path'          => $path,
                'title'         => $filename,
                'target'        => $target
            ]);

            ConvertVideoJob::dispatch($video)->onQueue('video');
        }

        if(isset($payload['spritemap']))
        {
            $spritemap = Video::create([
                'uid'           => $this->download->uid,
                'disk'          => 'uploaded',
                'mediakey'      => $payload['source']['mediakey'],
                'path'          => $path,
                'title'         => $filename,
                'target'        => $payload
            ]);

            CreateSpritemapJob::dispatch($spritemap)->onQUeue('video');
        }
    }
    public function failed($exception)
    {
        echo $exception->getMessage();
    }
}
