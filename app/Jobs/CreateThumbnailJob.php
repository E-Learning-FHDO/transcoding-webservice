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

class CreateThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    private $dimension;

    public function __construct(Video $video)
    {
        $this->video = $video;
        $this->dimension = new Dimension(10, 10);
    }

    public function handle()
    {
        $transcoder = new TranscodingController($this->video, $this->dimension, $this->attempts());
        $transcoder->createThumbnail();
    }

    public function failed(\Exception $exception)
    {
        echo $exception->getMessage();
    }

    public function jobs()
    {
        return $this->onQueue($this->queue);
    }
}
