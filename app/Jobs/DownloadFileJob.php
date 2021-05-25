<?php

namespace App\Jobs;

use App\Http\Controllers\TranscodingController;
use App\Models\Download;
use App\Models\Media;
use App\Models\Status;
use App\Models\User;
use GuzzleHttp\RequestOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use TheSeer\Tokenizer\Exception;
use Throwable;

class DownloadFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $download;

    public function __construct(Download $download)
    {
        $this->download = $download;
    }

    public function handle()
    {
        Log::info("Entering " . __METHOD__);
        $payload = $this->download->payload;
	    $start = now();
        $path = $payload['mediakey'];

        $user = User::find($this->download->user_id);
        $api_token = $user->api_token;

        Log::info("Starting download of mediakey " . $payload['mediakey']);

        if ($this->download->processing !== Status::PROCESSING) {
            try {

                $httpClient = new Client();

                Log::info("Processing download of " . $payload['mediakey']);

                $this->download->update(['processed' => Status::PROCESSING]);
                $response = $httpClient->post($payload['source']['url'], [
                    RequestOptions::JSON => [
                        'api_token' => $api_token,
                    ]
                ]);
                Log::debug(__METHOD__ .': '. $response->getReasonPhrase());
                Storage::disk('uploaded')->put($path, $response->getBody());
            } catch (Throwable $exception) {
                Log::info('Exception occurred while downloading: ' . $exception->getMessage());
                $httpClient = new Client();

                $url = $user->url . TranscodingController::TRANSCODERWEBSERVICE_CALLBACK;

                try {
                    $response = $httpClient->post($url, [
                        RequestOptions::JSON => [
                            'api_token' => $api_token,
                            'mediakey' => $this->download->mediakey,
                            'error' => [ 'message' => $exception->getMessage() ]
                        ]
                    ]);
                    Log::debug(__METHOD__ .': '. $response->getReasonPhrase());
                    $this->delete();
                    return;
                }
                catch (Exception $exception) {
                    Log::info('Exception occurred while sending callback: ' . $exception->getMessage());
                }
            }
        } else {
            Log::info($path . ' exists already, cancelling');
            $this->delete();
            return;
        }
        $time = $start->diffInSeconds(now());
        Log::debug("Download in " . __METHOD__ . " took $time seconds" );
        Log::info("Finished download of " . $payload['mediakey']);

        $this->dispatchCreateThumbnailJob($payload);
        $this->dispatchCreateSpritemapJob($payload);

        foreach ($payload['target']['format'] as $target) {
            $target['created_at'] = $payload['source']['created_at'];

            $this->dispatchConvertPreviewHLSVideoJob($payload, $target);
            $this->dispatchConvertPreviewVideoJob($payload, $target);
            $this->dispatchConvertHLSVideoJob($payload, $target);
            $this->dispatchConvertVideoJob($payload, $target);
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function failed(Throwable $exception): void
    {
        Log::debug("Entering " . __METHOD__);
        $this->download->update(['processed' => Status::FAILED]);
        $this->delete();
        Log::debug($exception->getMessage());
        Log::debug("Exiting " . __METHOD__);
    }

    public static function deleteAssociatedJobs($download_id): void
    {
        Log::debug("Entering " . __METHOD__);
        $download = Download::find($download_id);
        $downloadJobs = $download->jobs->all();

        foreach ($downloadJobs as $job) {
            Log::info('Deleting job ' . $job->id);
            try {
                Cache::lock('job-' . $job->id)->get(function () use ($download, $job) {
                    $download->jobs()->detach($job->id);
                    $job->delete();
                });
            } catch (\Exception $exception) {
                Log::debug("Failed to delete associated jobs " . $exception->getMessage());
            }
        }
        Log::debug("Exiting " . __METHOD__);
    }

    /**
     * @param $payload
     * @return mixed
     */
    protected function dispatchCreateSpritemapJob($payload)
    {
        if (isset($payload['spritemap'])) {
            $spritemap = Media::create([
                'user_id' => $this->download->user_id,
                'download_id' => $this->download->id,
                'disk' => 'uploaded',
                'mediakey' => $payload['mediakey'],
                'path' =>  $payload['mediakey'],
                'title' => "CreateSpritemapJob",
                'target' => $payload
            ]);

            $spritemapJobId = CreateSpritemapJob::dispatch($spritemap)->onQUeue(Media::QUEUE);
        }
        return $payload;
    }

    /**
     * @param $payload
     * @param $target
     * @return array
     */
    protected function dispatchConvertHLSVideoJob($payload, $target): array
    {
        if (isset($payload['target']['hls'])) {
            $media = Media::create([
                'user_id' => $this->download->user_id,
                'download_id' => $this->download->id,
                'disk' => 'uploaded',
                'mediakey' => $payload['mediakey'],
                'path' => $payload['mediakey'],
                'title' => "ConvertHLSVideoJob",
                'target' => $target
            ]);

            $hlsVideoJob = new ConvertVideoJob($media, false, true);
            $hlsVideoJob->onQUeue(Media::QUEUE);
            $hlsVideoJobId = dispatch($hlsVideoJob);
        }
        return $payload;
    }

    /**
     * @param $payload
     * @return mixed
     */
    protected function dispatchCreateThumbnailJob($payload)
    {
        if (isset($payload['thumbnail'])) {
            foreach ($payload['thumbnail'] as $key => $value) {
                $thumbnail = array();
                $thumbnail[$key] = $value;
                $payload['thumbnail_item'] = $thumbnail;
                $thumbnailItem = Media::create([
                    'user_id' => $this->download->user_id,
                    'download_id' => $this->download->id,
                    'disk' => 'uploaded',
                    'mediakey' => $payload['mediakey'],
                    'path' => $payload['mediakey'],
                    'title' => "CreateThumbnailJob",
                    'target' => $payload,
                ]);
                $thumbnailJobId = CreateThumbnailJob::dispatch($thumbnailItem)->onQUeue(Media::QUEUE);
            }
        }
        return $payload;
    }

    /**
     * @param $payload
     * @param $target
     * @return mixed
     */
    protected function dispatchConvertVideoJob($payload, $target)
    {
        $media = Media::create([
            'user_id' => $this->download->user_id,
            'download_id' => $this->download->id,
            'disk' => 'uploaded',
            'mediakey' => $payload['mediakey'],
            'path' => $payload['mediakey'],
            'title' => "ConvertVideoJob",
            'target' => $target
        ]);

        $videoJob = new ConvertVideoJob($media, false, false);
        $videoJob->onQueue(Media::QUEUE);
        $videoJobId = dispatch($videoJob);
        return $media;
    }

    /**
     * @param $payload
     * @param $target
     * @return mixed
     */
    protected function dispatchConvertPreviewVideoJob($payload, $target)
    {
        if (isset($payload['target']['start'], $payload['target']['duration'])) {
            $media = Media::create([
                'user_id' => $this->download->user_id,
                'download_id' => $this->download->id,
                'disk' => 'uploaded',
                'mediakey' => $payload['mediakey'],
                'path' => $payload['mediakey'],
                'title' => "ConvertPreviewVideoJob",
                'target' => $target
            ]);

            $previewVideoJob = new ConvertVideoJob($media, true, false);
            $previewVideoJob->onQueue(Media::QUEUE);
            $previewVideoJobId = dispatch($previewVideoJob);
        }
        return $payload;
    }

    private function dispatchConvertPreviewHLSVideoJob($payload, $target)
    {
        if (isset($payload['target']['hls'], $payload['target']['start'], $payload['target']['duration'])) {
            $media = Media::create([
                'user_id' => $this->download->user_id,
                'download_id' => $this->download->id,
                'disk' => 'uploaded',
                'mediakey' => $payload['mediakey'],
                'path' => $payload['mediakey'],
                'title' => "ConvertPreviewHLSVideoJob",
                'target' => $target
            ]);

            $previewHLSVideoJob = new ConvertVideoJob($media, true, true);
            $previewHLSVideoJob->onQueue(Media::QUEUE);
            $previewVideoJobId = dispatch($previewHLSVideoJob);
        }
        return $payload;
    }
}
