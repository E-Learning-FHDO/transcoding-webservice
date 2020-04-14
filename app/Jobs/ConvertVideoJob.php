<?php

namespace App\Jobs;

use App\Http\Controllers\TranscodingController;
use App\Models\Video;
use FFMpeg\Coordinate\Dimension;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ConvertVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    private $dimension;

    public function __construct(Video $video)
    {
        $this->video = $video;
        $target = $this->video->target;

        $size = explode('x', $target['size']);
        $this->dimension = new Dimension($size[0], $size[1]);
    }

    public function handle()
    {
        $transcoder = new TranscodingController($this->video, $this->dimension, $this->attempts());
        $transcoder->transcode();
    }

    public function failed($exception)
    {
        echo $exception->getMessage();
    }

    public function jobs()
    {
        return $this->onQueue($this->queue);
    }
}
