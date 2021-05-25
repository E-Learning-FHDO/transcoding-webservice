<?php

namespace App\Http\Controllers;

use Alchemy\BinaryDriver\Listeners\DebugListener;
use App\Format\Video\H264;
use App\Models\Download;
use App\Models\Profile;
use App\Models\Media;
use App\Models\Status;
use App\Models\Worker;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use FFMpeg\Coordinate;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Version\Package\Version;
use ProtoneMedia\LaravelFFMpeg\Exporters\EncodingException;
use ProtoneMedia\LaravelFFMpeg\Exporters\NoFormatException;
use ProtoneMedia\LaravelFFMpeg\Filesystem\MediaCollection;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use FFMpeg\Coordinate\Dimension;
use ZipArchive;
use Throwable;

class TranscodingController extends Controller
{
    public const TRANSCODERWEBSERVICE_CALLBACK = '/transcoderwebservice/callback';
    public const SPRITEMAP_DEFAULT_WIDTH = 142;
    public const SPRITEMAP_DEFAULT_HEIGHT = 80;

    public $media;
    private $dimension;
    private $preview;
    private $hls;
    private $user;
    private $profile;
    private $attempts;
    private $progress;
    private $pid;
    private $worker;

    public function __construct(Media $media, Dimension $dimension, $attempts)
    {
        $this->media = $media;
        $this->dimension = $dimension;
        $this->attempts = $attempts;
        $this->user = User::find($this->media->user_id);
        $this->profile = $this->user->profile;
        $this->worker = gethostname();
    }

    public function updateWorkerStatus(): void
    {
        Log::debug("Entering " . __METHOD__);
        try {
            Cache::lock('worker-' . $this->worker)->get(function () {
                $date = Carbon::now();
                Worker::updateOrCreate([
                    'host' => $this->worker
                ], [
                    'last_seen_at' => $date,
                    'description' => gethostbyname($this->worker)
                ]);
            });

        } catch (Throwable $exception) {
            Log::debug("Failed to update or create worker $this->worker: " . $exception->getMessage());
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function updateProgress($percentage): void
    {
        Log::debug("Entering " . __METHOD__);
        try {
            Cache::lock('target-' . $this->getTargetFileName())->get(function () use ($percentage) {
                $this->media->update([
                    'percentage' => $percentage,
                ]);
            });

        } catch (Throwable $exception) {
            Log::debug("Failed to update progress of " . $this->getTargetFileName() . ":" . $exception->getMessage());
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function transcode(): void
    {
        Log::debug("Entering " . __METHOD__);
        $this->pid = getmypid();

        $start = now();
        $this->updateWorkerStatus();
        $this->media->update([
            'processed' => Status::PROCESSING,
            'file' => $this->getTargetFileName(),
            'worker' => $this->worker
        ]);

        $target = $this->media->target;

        $convertedFileName = $this->getTargetFileName();
        Log::info("Clip: $convertedFileName, encoder: " . $this->profile->encoder . ", attempt: $this->attempts");

        $fallbackProfile = Profile::find($this->user->profile->fallback_id);
        if ($this->attempts > 1 && !empty($fallbackProfile)) {
            Log::info("Failed to encode $convertedFileName with " . $this->profile->encoder . " codec");
            $this->profile = $fallbackProfile;
        }
        Log::info("Trying to encode clip $convertedFileName with " . $this->profile->encoder . " codec ..");
        Log::debug("Target:  " . print_r($this->media->target, true));

        $h264 = (new H264('aac', $this->profile->encoder))
            ->setKiloBitrate($target['vbr'])
            ->setAudioKiloBitrate($target['abr'])
            ->setAdditionalParameters($this->applyAdditionalParameters())
            ->setInitialParameters($this->applyInitialParameters());

        if ($this->isHLS()) {
            $this->prepareHLSDirectory();
            $this->transcodeHLSVideo($h264, $convertedFileName);

        } else {
            $this->transcodeVideo($h264, $convertedFileName);
        }

        $time = $start->diffInSeconds(now());
        Log::debug("Conversion in " . __METHOD__ . " of " . $this->getTargetFileName() . " took $time seconds");
        $this->media->update([
            'converted_at' => Carbon::now(),
            'processed' => Status::PROCESSED,
            'percentage' => 100
        ]);

        Log::debug("Exiting " . __METHOD__);
    }

    public function createThumbnail(): void
    {
        Log::debug("Entering " . __METHOD__);
        $start = now();
        $payload = $this->media->target;
        $target = $payload['thumbnail_item'];

        $key = array_key_first($target);
        $convertedFileName = $this->media->path . '_' . $payload['source']['created_at'] . '_' . $key . '.jpg';

        $ffmpeg = FFMpeg::fromDisk('uploaded')
            ->open($this->media->path)
            ->getFrameFromSeconds($target[$key]['second'])
            ->export()
            ->toDisk('converted')
            ->save($convertedFileName)
            ->cleanupTemporaryFiles();

        $time = $start->diffInSeconds(now());
        Log::debug("Conversion in " . __METHOD__ . " of " . $convertedFileName . " took $time seconds", ['name' => $convertedFileName]);

        $this->media->update([
            'converted_at' => Carbon::now(),
            'processed' => Status::PROCESSED,
            'percentage' => 100,
            'file' => $convertedFileName,
            'worker' => $this->worker
        ]);

        if (config('app.callback_enabled')) {
            $httpClient = new Client();

            $response = $httpClient->post($this->user->url . self::TRANSCODERWEBSERVICE_CALLBACK, [
                RequestOptions::JSON => [
                    'api_token' => $this->user->api_token,
                    'mediakey' => $this->media->mediakey,
                    'thumbnail' => [
                        'url' => route('getFile', $convertedFileName)
                    ]
                ]
            ]);

            Log::debug(__METHOD__ . ': ' . $response->getReasonPhrase());

            if ($this->isDownloadComplete() && $this->media->download()->get('processed')) {
                $this->media->download()->update(['processed' => Status::PROCESSED]);
                $this->executeFinalCallback();
            }
        }

        Log::debug("Exiting " . __METHOD__);
    }

    public function createSpritemap()
    {
        Log::debug("Entering " . __METHOD__);

        $start = now();
        $payload = $this->media->target;
        $spritemap = $payload['spritemap'];

        $convertedFileName = $this->media->path . '_' . $payload['source']['created_at'] . '_sprites.jpg';

        $targetWidth = $spritemap['width'] ?? self::SPRITEMAP_DEFAULT_WIDTH;
        $targetHeight = $spritemap['height'] ?? self::SPRITEMAP_DEFAULT_HEIGHT;

        $duration = FFMpeg::fromDisk('uploaded')
            ->open($this->media->path)
            ->getDurationInSeconds();

        $fps = $spritemap['count'] / ceil($duration);

        $ffmpeg = FFMpeg::fromDisk('uploaded')
            ->open($this->media->path)
            ->getFrameFromSeconds(0)
            ->addFilter('-vf', 'select=eq(pict_type\,PICT_TYPE_I),mpdecimate,scale=' . $targetWidth . ':' . $targetHeight . ',fps=' . $fps . ',tile=10x10:margin=2:padding=2')
            ->export()
            ->toDisk('converted')
            ->save($convertedFileName)
            ->cleanupTemporaryFiles();

        Log::debug("Spritemap count: " . $spritemap['count'] . ", duration: " . $duration);

        $time = $start->diffInSeconds(now());
        Log::debug("Conversion in " . __METHOD__ . " of " . $convertedFileName . " took $time seconds", ['name' => $convertedFileName]);

        $this->media->update([
            'converted_at' => Carbon::now(),
            'processed' => Status::PROCESSED,
            'percentage' => 100,
            'file' => $convertedFileName,
            'worker' => $this->worker
        ]);

        if (config('app.callback_enabled')) {
            $httpClient = new Client();

            $response = $httpClient->post($this->user->url . self::TRANSCODERWEBSERVICE_CALLBACK, [
                RequestOptions::JSON => [
                    'api_token' => $this->user->api_token,
                    'mediakey' => $this->media->mediakey,
                    'spritemap' => [
                        'count' => $spritemap['count'],
                        'url' => route('getFile', $convertedFileName)
                    ]
                ]
            ]);

            Log::debug(__METHOD__ . ': ' . $response->getReasonPhrase());

            if ($this->isDownloadComplete() && $this->media->download()->get('processed')) {
                $this->media->download()->update(['processed' => Status::PROCESSED]);
                $this->executeFinalCallback();
            }
        }

        Log::debug("Exiting " . __METHOD__);
    }

    public function setPreview($preview = true): void
    {
        $this->preview = $preview;
    }

    public function isPreview()
    {
        return $this->preview;
    }

    public function setHLS($hls = true): void
    {
        $this->hls = $hls;
    }

    public function isHLS()
    {
        return $this->hls;
    }

    public function executeCallback(): void
    {
        Log::debug("Entering " . __METHOD__);
        $httpClient = new Client();
        $apiToken = $this->user->api_token;
        $url = $this->user->url . self::TRANSCODERWEBSERVICE_CALLBACK;

        if ($this->isHLS()) {
            $archiveFile = $this->createHLSArchive();

            $requestOptions = array(
                RequestOptions::JSON => [
                    'api_token' => $apiToken,
                    'mediakey' => $this->media->mediakey,
                    'medium' => [
                        'label' => $this->media->target['label'],
                        'url' => route('getFile', $archiveFile),
                        'hls' => true,
                        'vbr' => $this->media->target['vbr'],
                        'abr' => $this->media->target['abr'],
                        'size' => $this->media->target['size'],
                        'extension' => $this->media->target['extension'],
                        'created_at' => $this->media->target['created_at'],
                        'preview' => $this->isPreview() ?? false,
                        'default' => $this->media->target['default'] ?? false
                    ]
                ]);
        } else {

            $sourceFormat = FFMpeg::fromDisk('uploaded')->open($this->media->path)->getVideoStream();
            $targetFormat = FFMpeg::fromDisk('converted')->open($this->getTargetFileName())->getVideoStream();

            $tags = $targetFormat->get('tags');
            $orientation = empty($tags['rotate']) ? 0 : (int)$tags['rotate'];

            $requestOptions = array(
                RequestOptions::JSON => [
                    'api_token' => $apiToken,
                    'mediakey' => $this->media->mediakey,
                    'medium' => [
                        'label' => $this->media->target['label'],
                        'url' => route('getFile', $this->getTargetFileName()),
                        'preview' => $this->isPreview() ?? false,
                        'default' => $this->media->target['default'] ?? false
                    ],
                    'properties' => [
                        'source_width' => $sourceFormat->get('width'),
                        'source_height' => $sourceFormat->get('height'),
                        'duration' => round($targetFormat->get('duration'), 0),
                        'filesize' => Storage::disk('converted')->size($this->getTargetFileName()),
                        'width' => $targetFormat->get('width'),
                        'height' => $targetFormat->get('height'),
                        'orientation' => $orientation,
                        'vbitrate' => $targetFormat->get('bit_rate'),
                        'source_is360video' => $this->is360Video($sourceFormat)
                    ]
                ]);
        }

        $response = $httpClient->post($url, $requestOptions);

        Log::debug(__METHOD__ . ': ' . $response->getReasonPhrase());
        if ($this->isDownloadComplete() && $this->media->download()->get('processed')) {
            $this->media->download()->update(['processed' => Status::PROCESSED]);
            $this->executeFinalCallback();
            FFMpeg::cleanupTemporaryFiles();
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function executeFinalCallback(): void
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('Executing final callback for mediakey ' . $this->media->mediakey);
        $httpClient = new Client();

        $apiToken = $this->user->api_token;
        $url = $this->user->url . self::TRANSCODERWEBSERVICE_CALLBACK;

        $response = $httpClient->post($url, [
            RequestOptions::JSON => [
                'api_token' => $apiToken,
                'mediakey' => $this->media->mediakey,
                'finished' => true
            ]
        ]);

        Log::debug(__METHOD__ . ': ' . $response->getReasonPhrase());
        Log::debug("Exiting " . __METHOD__);
    }

    public static function executeErrorCallback($media, $message): void
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('Executing error callback for mediakey ' . $media->mediakey);
        $httpClient = new Client();

        $user = User::find($media->user_id);
        $apiToken = $user->api_token;
        $url = $user->url . self::TRANSCODERWEBSERVICE_CALLBACK;

        $response = $httpClient->post($url, [
            RequestOptions::JSON => [
                'api_token' => $apiToken,
                'mediakey' => $media->mediakey,
                'error' => ['message' => $message]
            ]
        ]);
        Log::debug(__METHOD__ . ': ' . $response->getReasonPhrase());
        Log::debug("Exiting " . __METHOD__);
    }

    public function isDownloadComplete(): bool
    {
        Log::debug("Entering " . __METHOD__);
        Log::info('Check if all downloads are complete for mediakey ' . $this->media->mediakey);
        try {
            $media = Media::where('mediakey', '=', $this->media->mediakey)->firstOrFail();
            $total = Media::where('download_id', $media->download_id)->count();
            $processed = Media::where('download_id', $media->download_id)->where('processed', Status::PROCESSED)->whereNotNull('downloaded_at')->count();
            if ($total === $processed) {
                Log::info('All downloads are complete for mediakey ' . $this->media->mediakey . " ($processed of $total)");
                Log::debug("Exiting " . __METHOD__);
                return true;
            }
            Log::info('Downloads are not yet complete for mediakey ' . $this->media->mediakey . " ($processed of $total)");
            Log::debug("Exiting " . __METHOD__);
            return false;
        } catch (\Exception $exception) {
            Log::info('Downloads are incomplete for mediakey ' . $this->media->mediakey);
            Log::debug("Exiting " . __METHOD__);
            return false;
        }
    }

    protected function is360Video($sourceFormat): bool
    {
        $is360Video = false;
        $sideDataList = $sourceFormat->get('side_data_list')[0] ?? null;
        if (isset($sideDataList["side_data_type"])) {
            $sideDataType = Arr::get($sideDataList, 'side_data_type');
            $is360Video = Str::contains($sideDataType, 'Spherical Mapping');
        }
        return $is360Video;
    }

    protected function applyInitialParameters(): array
    {
        $storedProfileOptions = $this->profile->options->pluck('value', 'key')->toArray();

        $profileOptions = array();
        foreach ($storedProfileOptions as $key => $value) {
            $profileOptions[] = $key;
            $profileOptions[] = $value;
        }
        if ($this->preview) {
            $profileOptions[] = '-ss';
            $profileOptions[] = \FFMpeg\Coordinate\TimeCode::fromSeconds($this->media->download()->get()->first()->payload['target']['start']);
        }

        return $profileOptions;
    }

    protected function applyAdditionalParameters(): array
    {
        $payload = $this->media->download()->get()->first()->payload;
        $storedProfileAdditionalParameters = $this->profile->additionalparameters->pluck('value', 'key')->toArray();
        $profileAdditionalParameters = array();
        foreach ($storedProfileAdditionalParameters as $key => $value) {
            $profileAdditionalParameters[] = $key;
            $profileAdditionalParameters[] = $value;
        }
        if ($this->isPreview()) {
            $profileAdditionalParameters[] = '-t';
            $profileAdditionalParameters[] = \FFMpeg\Coordinate\TimeCode::fromSeconds($payload['target']['duration']);
        }

        return $profileAdditionalParameters;
    }

    protected function getTargetFileName($playlist = false): string
    {
        $target = $this->media->target;
        $separator = '_';

        if (isset($target['default']) && $target['default'] == true) {
            $target['label'] = '';
            $separator = '';
        }

        $file = $this->media->path . '_' . $target['created_at'] . $separator . $target['label'] . '.' . $target['extension'];

        if ($this->isHLS()) {
            $preview = $this->isPreview() ? 'preview_' : '';
            $main = ($playlist === true) ? 'main_' : '';
            $file = $this->getHLSDirectoryName() . DIRECTORY_SEPARATOR . $main . $preview . $this->media->path . '_' . $target['created_at'] . $separator . $target['label'] . '_' . $target['extension'] . '.m3u8';

            return $file;
        }

        if ($this->isPreview()) {
            $file = 'preview_' . $file;
        }
        return $file;
    }

    private function applyFilters(): string
    {
        $w = $this->dimension->getWidth();
        $h = $this->dimension->getHeight();
        switch ($this->profile->encoder) {
            case 'h264_vaapi':
            {
                return 'scale_vaapi=w=\'if(gt(a\,' . $w . '/' . $h . ')\,' . $w . '\,oh*a)\':h=\'if(gt(a\,' . $w . '/' . $h . ')\,ow/a\,' . $h . ')\'';
            }

            case 'h264_nvenc':
            {
                return 'hwupload,scale_npp=w=' . $w . ':h=' . $h . ':force_original_aspect_ratio=decrease:force_divisible_by=2:interp_algo=super';
            }

            default:
            {
                return 'scale=w=' . $w . ':h=' . $h . ':force_original_aspect_ratio=decrease,crop=\'iw-mod(iw\,2)\':\'ih-mod(ih\,2)\'';
            }
        }
    }

    private function prepareHLSDirectory(): void
    {
        if ($this->isHLS()) {
            Storage::disk('converted')->deleteDirectory($this->getHLSDirectoryName());
        }
    }

    private function getHLSDirectoryName(): string
    {
        $preview = $this->isPreview() ? 'preview_' : '';

        return $preview . $this->media->path . '_' . $this->media->target['label'] . '_' . $this->media->target['extension'];
    }

    /**
     * @return string
     */
    protected function createHLSArchive(): string
    {
        $archiveFile = $this->getHLSDirectoryName() . '.zip';
        $this->media->update([
            'file' => $archiveFile
        ]);
        return $archiveFile;
    }

    /**
     * @param H264 $h264
     * @param string $convertedFileName
     */
    private function transcodeHLSVideo(H264 $h264, string $convertedFileName): void
    {
        if (!Storage::disk('converted')->exists($this->getHLSDirectoryName())) {
            Storage::disk('converted')->makeDirectory($this->getHLSDirectoryName());
        }

        $playlistFileName = $this->getTargetFileName();
        $tsFileName = substr($this->getTargetFileName(), 0, -5) . '_%03d.ts';

        $ffmpeg = FFMpeg::fromDisk('uploaded')
            ->open($this->media->path)
            ->exportForHLS()
            ->useSegmentFilenameGenerator(function ($name, $format, $key, callable $segments, callable $playlist) use ($tsFileName, $playlistFileName) {
                $segments($tsFileName);
                $playlist($playlistFileName);
            })
            ->setSegmentLength(4)
            ->toDisk('converted')
            ->addFormat($h264, function ($media) {
                $media->addFilter(function ($filters, $in, $out) {
                    $filters->custom($in, $this->applyFilters(), $out); // $in, $parameters, $out
                });
            })
            ->onProgress(function ($percentage) use ($convertedFileName) {
                $this->checkProcess($this->pid);
                if ($percentage !== 0) {
                    Log::info("Host: {$this->worker}, PID:  7$this->pid}, {$percentage}% of {$convertedFileName} transcoded");
                    $this->progress = (int)$percentage;
                    $this->updateProgress($percentage);
                }
            })
            ->beforeSaving(function ($commands) {
                $last[] = array_pop($commands);
                return array_merge($this->applyInitialParameters(), $commands, $this->applyAdditionalParameters(), $last);
            })
            ->save($this->getTargetFileName(true))
            ->cleanupTemporaryFiles();
    }

    /**
     * @param H264 $h264
     * @param string $convertedFileName
     */
    private function transcodeVideo(H264 $h264, string $convertedFileName): void
    {
        $ffmpeg = FFMpeg::fromDisk('uploaded')->open($this->media->path)
            ->export()
            ->addFilter('-vf', $this->applyFilters())
            ->onProgress(function ($percentage, $remaining, $rate) use ($convertedFileName) {
                $this->checkProcess($this->pid);
                if ($percentage !== 0) {
                    Log::info("Host: {$this->worker}, PID: {$this->pid}, {$percentage}% of {$convertedFileName} transcoded, {$remaining}s remaining, rate: {$rate}");
                    $this->progress = (int)$percentage;
                    $this->updateProgress($percentage);
                }
            })
            ->inFormat($h264)
            ->toDisk('converted')
            ->save($this->getTargetFileName())
            ->cleanupTemporaryFiles();
    }


    private function checkProcess($pid): void
    {
        $media = Media::find($this->media->id);
        if (empty($media)) {
            Log::debug('Recursively killing process with pid ' . $pid);
            $this->recursiveKill($pid);
        }
    }

    private function recursiveKill($pid): void
    {
        $child_pid_list = array();
        exec('pgrep -P ' . $this->pid, $child_pid_list);
        foreach ($child_pid_list as $child_pid) {
            if (preg_match('#[0-9]+#', $child_pid)) {
                $this->recursiveKill($child_pid);
            }
        }
        exec('kill -9 ' . $pid);
    }
}
