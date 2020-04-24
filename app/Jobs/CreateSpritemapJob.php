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

    public function __construct(Video $video)
    {
        $this->video = $video;
        $this->dimension = new Dimension(10, 10);
    }

    public function handle()
    {
        try {
            DownloadJob::create([
                'download_id' => $this->video->download_id,
                'job_id' => $this->job->getJobId()
            ]);

            $transcoder = new TranscodingController($this->video, $this->dimension, $this->attempts());
            $transcoder->createSpritemap();

        } catch (\Exception $exception) {

            echo $exception->getMessage();
            Log::info('One or more steps in jobs with download_id ' . $this->video->download_id . ' failed, cancelling');
            VideoController::deleteAllByMediaKey($this->video->mediakey);
        } finally {
            $downloadJob = DownloadJob::where('download_id', $this->video->download_id)->where('job_id', $this->job->getJobId());
            $downloadJob->delete();
            $this->delete();
        }
    }

    public function failed(\Exception $exception)
    {

    }

    public function jobs()
    {
        return $this->onQueue($this->queue);
    }
}
