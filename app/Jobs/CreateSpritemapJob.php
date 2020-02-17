<?php

namespace App\Jobs;

use FFMpeg;
use App\Models\Video;
use App\Format\Video\H264;
use Carbon\Carbon;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Filters\Frame\CustomFrameFilter;
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

class CreateSpritemapJob implements ShouldQueue
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
        $spritemap = $payload['spritemap'];

        $converted_name = $this->video->path . '_' . $payload['source']['created_at'] . '_sprites.jpg';

        $target_width = isset($spritemap['width']) ? $spritemap['width'] : 142;
        $target_height = isset($spritemap['height']) ? $spritemap['height'] : 80;

        $ffmpeg = FFMpeg\FFMpeg::create();
        $ffprobe = FFMpeg\FFProbe::create();
        $source_format = $ffprobe
            ->streams(Storage::disk('uploaded')->path($this->video->path))
            ->videos()
            ->first();

        $video = $ffmpeg->open(Storage::disk('uploaded')->path($this->video->path));
        $fps = $spritemap['count'] / ceil($source_format->get('duration'));

        $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(0))
            ->addFilter(new CustomFrameFilter('scale='. $target_width . ':'. $target_height . ',fps='. $fps .',tile=10x10:margin=2:padding=2'))
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
                'spritemap' => [
                    'count' => $spritemap['count'],
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
