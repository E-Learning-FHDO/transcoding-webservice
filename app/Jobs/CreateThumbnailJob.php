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
	try {
        	$transcoder->createThumbnail();
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

    }

    public function failed(\Exception $exception)
    {

    }

    private function failAll()
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('One or more steps of CreateThumbnailJob with download_id ' . $this->video->download_id . ' failed, cancelling all related jobs');
        DownloadFileJob::killAssociatedJobs($this->video->download_id);
        VideoController::deleteAllByMediaKey($this->video->mediakey);
        $downloadJob = DownloadJob::where('download_id', $this->video->download_id)->where('job_id', $this->job->getJobId());
        $downloadJob->delete();
        $this->delete();
        Log::debug("Exiting " . __METHOD__);
    }


    public function jobs()
    {
        return $this->onQueue($this->queue);
    }
}
