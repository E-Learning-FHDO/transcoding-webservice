<?php

namespace App\Jobs;

use App\Http\Controllers\VideoController;
use App\Models\DownloadJob;
use App\Models\Video;
use Carbon\Carbon;
use FFMpeg\Coordinate\Dimension;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\TranscodingController;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConvertPreviewVideoJob implements ShouldQueue
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

        if (!$this->video->getAttribute('converted_at') && !$existingFailedJobs) {
            try
            {
                if($this->video->processed !== Video::PROCESSING) {
                    DownloadJob::create([
                        'download_id' => $this->video->download_id,
                        'job_id' => $this->job->getJobId()
                    ]);

                    $this->transcoder = new TranscodingController($this->video, $this->dimension, $this->attempts());
                    $this->transcoder->setPreview(true);
                    $this->transcoder->transcode();
                    $this->transcoder->executeCallback();
                }
            }
            catch (Throwable $exception)
            {
                Log::info("PreviewVideoJob Message: " . $exception->getMessage() . ", Code: " . $exception->getCode() . ", Attempt: " . $this->attempts() . ", Class: " . get_class($exception) . ", Trace: " . $exception->getTraceAsString());
                $this->video->update(['processed' => Video::FAILED]);
                $this->job->release();
            }
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

    public function jobs()
    {
        return $this->onQueue($this->queue);
    }

    private function failAll()
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('One or more steps in jobs with download_id ' . $this->video->download_id . ' failed, cancelling');
        DownloadFileJob::killAssociatedJobs($this->video->download_id);
        VideoController::deleteAllByMediaKey($this->video->mediakey);
        $downloadJob = DownloadJob::where('download_id', $this->video->download_id)->where('job_id', $this->job->getJobId());
        $downloadJob->delete();
        $this->delete();
        Log::debug("Exiting " . __METHOD__);
    }
}
