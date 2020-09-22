<?php

namespace App\Jobs;

use App\Http\Controllers\TranscodingController;
use App\Http\Controllers\VideoController;
use App\Models\DownloadJob;
use App\Models\Video;
use Carbon\Carbon;
use FFMpeg\Coordinate\Dimension;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class CreateSpritemapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    private $dimension;

    private $transcoder;

    public function __construct(Video $video)
    {
        $this->video = $video;
        $this->dimension = new Dimension(10, 10);
    }

    public function handle()
    {
        Log::debug("Entering " . __METHOD__);
        $this->transcoder = new TranscodingController($this->video, $this->dimension, $this->attempts());
        try
        {
            $this->transcoder->createSpritemap();
        }

        catch (\Exception $exception)
        {
            Log::info("CreateThumbnailJob Message: " . $exception->getMessage() . ", Code: " . $exception->getCode() . ", Attempt: " . $this->attempts());
            $this->video->update(['processed' => Video::FAILED]);

            Log::info('Exception ' . $exception->getTraceAsString());
            $this->failAll();
            $this->transcoder->executeErrorCallback($exception->getMessage());
            $this->video->update(['failed_at' => Carbon::now()]);
            $this->job->release();
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function failed(\Exception $exception)
    {

    }

    public function jobs()
    {
        return $this->onQueue($this->queue);
    }
}
