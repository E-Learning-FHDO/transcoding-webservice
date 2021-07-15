<?php

namespace App\Console\Commands;

use App\Models\Status;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Download;
use App\Models\Media;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Client;
use App\Models\User;

class CleanupTranscode extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'transcode:cleanup';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Cleanup after transcoding tasks';

    /**
     * Create a new command instance.
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * @return mixed
     */
    public function handle()
    {
        Log::debug("Entering " . __METHOD__);
        $this->handleProcessingFinishedDownloads();
        $this->handleProcessedDownloads();
        $this->handleFailedDownloads();
        Log::debug("Exiting " . __METHOD__);
    }

    protected function handleFailedDownloads()
    {
        if (config('app.callback_enabled')) {
            $downloads = Download::where('processed', '=', Status::FAILED)->get();
            foreach ($downloads as $download) {
                $user = User::find($download->user_id);
                $apiToken = $user->api_token;
                $url = $user->url . '/transcoderwebservice/callback';
                try {
                    $httpClient = new Client();
                    $response = $httpClient->post($url, [
                        RequestOptions::JSON => [
                            'api_token' => $apiToken,
                            'mediakey' => $download->mediakey,
                            'error' => ['message' => 'An error occurred while downloading']
                        ]
                    ]);
                    Log::debug(__METHOD__ . ': ' . $response->getReasonPhrase());
                } catch (\Exception $exception) {
                    Log::debug(__METHOD__ . ': ' . $exception->getMessage());
                } finally {
                    $download->delete();
                }
            }
        }
    }

    protected function handleProcessedDownloads()
    {
        if (config('app.callback_enabled')) {
            $downloads = Download::where('processed', '=', Status::PROCESSED)->get();
            foreach ($downloads as $download) {
                $user = User::find($download->user_id);
                $apiToken = $user->api_token;
                $url = $user->url . '/transcoderwebservice/callback';

                try {
                    $httpClient = new Client();
                    $response = $httpClient->post($url, [
                        RequestOptions::JSON => [
                            'api_token' => $apiToken,
                            'mediakey' => $download->mediakey,
                            'finished' => true
                        ]
                    ]);
                    Log::debug(__METHOD__ . ': ' . $response->getReasonPhrase());
                } catch (\Exception $exception) {
                    Log::debug(__METHOD__ . ': ' . $exception->getMessage());
                }
            }
        }
    }

    protected function handleProcessingFinishedDownloads()
    {
        $downloads = Download::where('processed', '=', Status::PROCESSING)->get();
        foreach ($downloads as $download) {
            $videos = $download->videos()->get()->all();
            foreach ($videos as $video) {
                $total = $video->count();
                $processed = $video->where('processed', Status::PROCESSED)->whereNotNull('downloaded_at')->count();
                if ($total === $processed) {
                    if ($download->processed === Status::PROCESSING) {
                        Log::info('All downloads are complete for mediakey ' . $video->mediakey . " ($processed of $total)");
                        $download->update(['processed' => Status::PROCESSED]);
                    }
                }
            }
        }
    }
}
