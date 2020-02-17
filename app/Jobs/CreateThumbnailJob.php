<?php

namespace App\Jobs;

use FFMpeg;
use App\Models\Video;
use App\Format\Video\H264;
use Carbon\Carbon;
use FFMpeg\Coordinate\Dimension;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CreateThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    public function __construct(Video $video)
    {
        $this->video = $video;
    }

    public function handle()
    {
        $payload = $this->video->target;
        $target = $payload['thumbnail_item'];

        $key = array_key_first($target);
        $converted_name = $this->video->path . '_'. $payload['source']['created_at'] .'_' . $key. '.jpg';

        FFMpeg::fromDisk($this->video->disk)
            ->open($this->video->path)
            ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds($target[$key]['second']))
            ->save(Storage::disk('converted')->path($converted_name));

        $this->video->update([
            'converted_at' => Carbon::now(),
            'processed' => true,
            'file' => $converted_name
        ]);

        $guzzle = new Client();

        $api_token = DB::table('users')->where('id', $this->video->uid)->pluck('api_token')->first();
        $url = DB::table('users')->where('api_token', $api_token)->pluck('url')->first() . '/transcoderwebservice/callback';

        $response = $guzzle->post($url, [
            RequestOptions::JSON => [
                'api_token' => $api_token,
                'mediakey' => $this->video->mediakey,
                'thumbnail' => [
                    'url' =>  route('getFile', $converted_name)
                ]
            ]
        ]);

    }

    public function failed($exception)
    {
        echo $exception->getMessage();
    }

    public function jobs()
    {
        return $this->onQueue();
    }
}
