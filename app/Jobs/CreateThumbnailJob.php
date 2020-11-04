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
use Throwable;

class CreateThumbnailJob implements ShouldQueue
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
        	$this->transcoder->createThumbnail();
        }

        catch (Throwable $exception)
        {
            Log::info("CreateThumbnailJob Message: " . $exception->getMessage() . ", Code: " . $exception->getCode() . ", Attempt: " . $this->attempts() . ", Class: " . get_class($exception) . ", Trace: " . $exception->getTraceAsString());
            $this->video->update(['processed' => Video::FAILED]);
            $this->job->release();
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function failed(Throwable $exception)
    {
        Log::debug("Entering " . __METHOD__);
        $this->video->update(['processed' => Video::FAILED]);
        $this->failAll();
        TranscodingController::executeErrorCallback($this->video, $exception->getMessage());
        Log::debug("Exiting " . __METHOD__);
    }

    private function failAll()
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('One or more steps of CreateThumbnailJob with download_id ' . $this->video->download_id . ' failed, cancelling all related jobs');
        DownloadFileJob::killAssociatedJobs($this->video->download_id);
        VideoController::deleteAllByMediaKey($this->video->mediakey);
        $this->delete();
        Log::debug("Exiting " . __METHOD__);
    }


    public function jobs()
    {
        return $this->onQueue($this->queue);
    }
}
