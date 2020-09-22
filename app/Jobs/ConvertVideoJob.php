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

class ConvertVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    private $dimension;

    private $transcoder;

    public function __construct(Video $video)
    {
        $this->video = $video;
        $target = $this->video->target;

        $size = explode('x', $target['size']);
        $this->dimension = new Dimension($size[0], $size[1]);
    }

    public function handle()
    {
        Log::debug("Entering " . __METHOD__);
        $existingFailedJobs = Video::where('download_id', '=', $this->video->download_id)->whereNotNull('failed_at')->count() > 0;

        if(!$this->video->getAttribute('converted_at') && !$existingFailedJobs)
        {
            try
            {
                if($this->video->processed !== Video::PROCESSING)
                {
                    DownloadJob::create([
                        'download_id' => $this->video->download_id,
                        'job_id' => $this->job->getJobId()
                    ]);

                    $this->transcoder = new TranscodingController($this->video, $this->dimension, $this->attempts());
                    $this->transcoder->transcode();
                    $this->transcoder->executeCallback();
                }
            }
            catch (\Exception $exception)
            {
                Log::info("ConvertVideoJob Message: " . $exception->getMessage() . ", Code: " . $exception->getCode() . ", Attempt: " . $this->attempts() . ", Class: " . get_class($exception));
                $this->video->update(['processed' => Video::FAILED]);

                if(is_a($exception, '\GuzzleHttp\Exception\ClientException'))
                {
         	    Log::info('HTTP Client exception for download_id ' . $this->video->download_id);
                    $this->failAll();
                }

                if($this->attempts() > 1)
                {
	                Log::info('Maximal attempts for download_id ' . $this->video->download_id);
                    Log::info('Exception ' . $exception->getTraceAsString());
                    $this->failAll();
                    $this->transcoder->executeErrorCallback($exception->getMessage());
                }

                if(!$exception->getMessage() === 'Encoding failed')
                {
                    $this->video->update(['failed_at' => Carbon::now()]);
                }
                $this->job->release();
            }
        } else {
            $this->failAll();
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function failed($exception)
    {
	    Log::debug("Entering " . __METHOD__);
    }

    public function jobs()
    {
        return $this->onQueue($this->queue);
    }

    private function failAll()
    {
	    Log::debug("Entering " . __METHOD__);
        Log::info('One or more steps of ConvertVideoJob with download_id ' . $this->video->download_id . ' failed, cancelling all related jobs');
        DownloadFileJob::killAssociatedJobs($this->video->download_id);
        VideoController::deleteAllByMediaKey($this->video->mediakey);
        $downloadJob = DownloadJob::where('download_id', $this->video->download_id)->where('job_id', $this->job->getJobId());
        $downloadJob->delete();
        $this->delete();
        Log::debug("Exiting " . __METHOD__);
    }
}
