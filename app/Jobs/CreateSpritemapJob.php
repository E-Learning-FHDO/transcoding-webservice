<?php

namespace App\Jobs;

use App\Http\Controllers\TranscodingController;
use App\Http\Controllers\MediaController;
use App\Models\DownloadJob;
use App\Models\Media;
use App\Models\Status;
use Carbon\Carbon;
use FFMpeg\Coordinate\Dimension;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateSpritemapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $media;

    private $dimension;

    public function __construct(Media $media)
    {
        $this->media = $media;
        $this->dimension = new Dimension(10, 10);
    }

    public function handle()
    {
        Log::debug("Entering " . __METHOD__);
        $transcoder = new TranscodingController($this->media, $this->dimension, $this->attempts());
        try
        {
            $transcoder->createSpritemap();
        }

        catch (Throwable $exception)
        {
            Log::info("CreateSpritemapJob Message: "
                . $exception->getMessage() . ", Code: "
                . $exception->getCode() . ", Attempt: "
                . $this->attempts() . ", Class: "
                . get_class($exception) . ", Trace: "
                . $exception->getTraceAsString());

            $this->media->update(['processed' => Status::FAILED]);
            $this->job->release();
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function failed(Throwable $exception)
    {
        Log::debug("Entering " . __METHOD__);
        $this->media->update(['processed' => Status::FAILED]);
        $this->failAll();
        if (config('app.callback_enabled')) {
            TranscodingController::executeErrorCallback($this->media, $exception->getMessage());
        }
        Log::debug("Exiting " . __METHOD__);
    }

    private function failAll()
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('One or more steps of CreateSpritemapJob with download_id ' . $this->media->download_id . ' failed, cancelling all related jobs');
        DownloadFileJob::deleteAssociatedJobs($this->media->download_id);
        MediaController::deleteAllByMediaKey($this->media->mediakey);
        $this->delete();
        Log::debug("Exiting " . __METHOD__);
    }

    public function jobs()
    {
        return $this->onQueue($this->queue);
    }
}
