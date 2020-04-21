<?php

namespace App\Jobs;

use App\Http\Controllers\TranscodingController;
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
        $existingFailedJobs = Video::where('download_id', '=', $this->video->download_id)->whereNotNull('failed_at')->count() > 0;

        if (!$this->video->getAttribute('converted_at') && !$existingFailedJobs) {
            try
            {
                $transcoder = new TranscodingController($this->video, $this->dimension, $this->attempts());
                $transcoder->createThumbnail();
            }
            catch (\Exception $exception)
            {
                echo $exception->getMessage();
                $this->video->update(['failed_at' => Carbon::now()]);
            }

        } else {
            Log::info('One or more steps in jobs with download_id ' . $this->video->download_id . ' failed, cancelling');
        }
    }

    public function failed(\Exception $exception)
    {
        echo "Exception: " . $exception->getMessage();
        echo "Code: " . $exception->getCode();
        $this->delete();
        $this->video->update(['failed_at' => Carbon::now()]);
    }

    public function jobs()
    {
        return $this->onQueue($this->queue);
    }
}
