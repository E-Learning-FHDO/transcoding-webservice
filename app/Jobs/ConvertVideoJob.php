<?php

namespace App\Jobs;

use App\Http\Controllers\TranscodingController;
use App\Http\Controllers\MediaController;
use App\Models\Download;
use App\Models\DownloadJob;
use App\Models\Status;
use App\Models\User;
use App\Models\Media;
use Carbon\Carbon;
use FFMpeg\Coordinate\Dimension;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use GuzzleHttp\Exception\ClientException;

class ConvertVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $media;

    private $dimension;

    private $params;

    private $preview;

    private $hls;

    public function __construct(Media $media, $preview = false, $hls = false)
    {
        $this->params = func_get_args();

        $this->preview = $preview;
        $this->hls = $hls;
        $this->media = $media;
        $target = $this->media->target;

        $size = explode('x', $target['size']);
        $this->dimension = new Dimension($size[0], $size[1]);
    }

    public function handle(): void
    {
        Log::debug("Entering " . __METHOD__);
        $existingFailedJobs = Media::where('download_id', '=', $this->media->download_id)->whereNotNull('failed_at')->count() > 0;

        if (!$this->media->getAttribute('converted_at') && !$existingFailedJobs) {
            try {
                $profile = User::find($this->media->user_id)->profile;
                $profileWorker = $profile->workers->all();
                $match = false;
                if (count($profileWorker) > 0) {
                    foreach ($profileWorker as $worker) {
                        if ($worker->host === gethostname()) {
                            $match = true;
                            break;
                        }
                    }
                    if (!$match) {
                        throw new \Exception('Requeue', 15);
                    }
                }

                if ($this->media->processed !== Status::PROCESSING) {
                    $download = Download::find($this->media->download_id);
                    $download->jobs()->attach($this->job->getJobId());

                    $transcoder = new TranscodingController($this->media, $this->dimension, $this->attempts());
                    $transcoder->setHLS($this->hls);
                    $transcoder->setPreview($this->preview);
                    $transcoder->transcode();
                    if (config('app.callback_enabled')) {
                        $transcoder->executeCallback();
                    }
                }
            } catch (Throwable $exception) {
                if ($exception->getMessage() === 'Requeue') {
                    $createdAt = Carbon::parse($this->media->download->created_at);
                    $createdAt->setTimezone(config('app.timezone'));
                    $cancelTime = $createdAt->addHour()->diffInMinutes(now());
                    if ($createdAt->addHour()->greaterThan(now())) {
                        Log::debug($this->getJobTitle() . " is waiting {$cancelTime} minutes for being picked up by assigned worker...");
                        Cache::lock('job-' . $this->job->getJobId())->get(function () use ($exception) {
                            $this->media->update(['processed' => Status::WAITING]);
                            self::dispatch(...$this->params)->onConnection($this->connection)->onQueue($this->queue)->delay(15);
                        });
                        Log::debug("Exiting " . __METHOD__);
                        return;
                    }
                    Log::debug($this->getJobTitle() . " was not picked up by assigned worker within one hour");
                    $this->media->update(['processed' => Status::FAILED]);
                    Log::debug("Exiting " . __METHOD__);
                    return;
                }

                Log::info($this->getJobTitle()
                    . " Message: " . $exception->getMessage()
                    . ", Code: " . $exception->getCode()
                    . ", Attempt: " . $this->attempts()
                    . ", Class: " . get_class($exception)
                    . ", Trace: " . $exception->getTraceAsString());

                Cache::lock('job-' . $this->job->getJobId())->get(function () use ($exception) {
                    $this->media->update(['processed' => Status::FAILED]);
                    $this->job->release();
                });
            }
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function failed(Throwable $exception): void
    {
        Log::debug("Entering " . __METHOD__);
        $this->media->update(['processed' => Status::FAILED]);
        //$this->delete();
        $this->failAll();
        if (config('app.callback_enabled')) {
            TranscodingController::executeErrorCallback($this->media, $exception->getMessage());
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function jobs()
    {
        return $this->onQueue($this->queue);
    }

    private function failAll(): void
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('One or more steps of ' . $this->getJobTitle() . ' with download_id ' . $this->media->download_id . ' failed, cancelling all related jobs');
        DownloadFileJob::deleteAssociatedJobs($this->media->download_id);
        MediaController::deleteAllByMediaKey($this->media->mediakey);
        //$downloadJob = DownloadJob::where('download_id', $this->video->download_id)->where('job_id', $this->job->getJobId());
        //$downloadJob->delete();
        //$this->delete();
        Log::debug("Exiting " . __METHOD__);
    }

    private function getJobTitle(): string
    {
        $title = 'Convert';
        if ($this->preview) {
            $title .= 'Preview';
        }
        if ($this->hls) {
            $title .= 'HLS';
        }
        return $title . 'VideoJob';
    }
}
