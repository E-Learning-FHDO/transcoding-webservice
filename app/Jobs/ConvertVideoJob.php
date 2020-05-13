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
                Log::info("ConvertVideoJob Message: " . $exception->getMessage() . ", Code: " . $exception->getCode() . ", Attempt: " . $this->attempts());
                $this->video->update(['processed' => Video::FAILED]);

                if(is_a($exception, '\GuzzleHttp\Exception\ClientException'))
                {
                    $this->failAll();
                }

                if($this->attempts() > 1)
                {
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
    }

    public function failed($exception)
    {

    }

    public function jobs()
    {
        return $this->onQueue($this->queue);
    }

    private function failAll()
    {
        Log::info('One or more steps in jobs with download_id ' . $this->video->download_id . ' failed, cancelling');
        DownloadFileJob::killAssociatedJobs($this->video->download_id);
        VideoController::deleteAllByMediaKey($this->video->mediakey);
        $downloadJob = DownloadJob::where('download_id', $this->video->download_id)->where('job_id', $this->job->getJobId());
        $downloadJob->delete();
        $this->delete();
    }
}
