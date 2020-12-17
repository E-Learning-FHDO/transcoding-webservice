<?php

namespace App\Http\Controllers;

use Alchemy\BinaryDriver\Listeners\DebugListener;
use App\Format\Video\H264;
use App\Models\Download;
use App\Models\Profile;
use App\Models\Video;
use App\Models\Worker;
use App\User;
use Carbon\Carbon;
use Exception;
use FFMpeg\Filters\Frame\CustomFrameFilter;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use FFMpeg;
use FFMpeg\Coordinate\Dimension;
use ZipArchive;
use Throwable;

class TranscodingController extends Controller
{
    public const TRANSCODERWEBSERVICE_CALLBACK = '/transcoderwebservice/callback';
    public const SPRITEMAP_DEFAULT_WIDTH = 142;
    public const SPRITEMAP_DEFAULT_HEIGHT = 80;

    public $video;
    private $dimension;
    private $preview;
    private $hls;
    private $user;
    private $profile;
    private $attempts;
    private $progress;
    private $pid;
    private $host;

    public function __construct(Video $video, Dimension $dimension, $attempts)
    {
        $this->video = $video;
        $this->dimension = $dimension;
        $this->attempts = $attempts;
        $this->user = User::find($this->video->user_id);
        $this->profile = $this->user->profile;
        $this->host = gethostname();
    }

    public function updateWorkerStatus()
    {

        try {
            $date = Carbon::now();

            Log::debug('Transaction begin for create host ' . $this->host . ' and date ' . $date);
            $worker = Worker::create([
                'host' => $this->host,
                'last_seen_at' => $date,
                'description' => gethostbyname($this->host)
            ]);
        }
        catch(Throwable $exception)
        {
            Log::debug('Transaction begin for update host ' . $this->host . ' and date ' . $date);

            $worker = Worker::where('host', '=', $this->host)->find(1);
            if ($worker !== null) {
                $worker->update(['last_seen_at' => $date]);
            }
        }
    }

    public function transcode()
    {
        Log::debug("Entering " . __METHOD__);
        $pid = $this->pid = getmypid();

        $this->updateWorkerStatus();
        $this->video->update([
            'processed' => Video::PROCESSING,
            'file' => $this->getTargetFile(),
            'host' => $this->host
        ]);

        $this->prepare();

        $target = $this->video->target;

        $converted_name = $this->getTargetFile();
        Log::info("Clip: $converted_name, encoder: " . $this->profile->encoder . ", attempt: $this->attempts");
        $fallback_profile = Profile::find($this->user->profile->fallback_id);
        if ($this->attempts > 1  && !empty($fallback_profile)) {
            Log::info("Failed to encode $converted_name with " . $this->profile->encoder . " codec");
            $this->profile = $fallback_profile;
        }
        Log::info("Trying to encode clip $converted_name with " . $this->profile->encoder . " codec ..");
        Log::debug("Target:  ". print_r($this->video->target, true));

        $h264 = (new H264('aac', $this->profile->encoder))
            ->setKiloBitrate($target['vbr'])
            ->setAudioKiloBitrate($target['abr'])
            ->setAdditionalParameters($this->applyAdditionalParameters())
            ->setInitialParameters($this->applyInitialParameters());

        $ffmpeg = FFMpeg\FFMpeg::create(self::getFFmpegConfig());
        if(self::getFFmpegConfig()['ffmpeg.debug']) {
            $ffmpeg->getFFMpegDriver()->listen(new DebugListener());
                $ffmpeg->getFFMpegDriver()->on('debug', function ($message) {
                    Log::info('FFmpeg: ' . $message);
                });
        }

        $videofile = Storage::disk('uploaded')->path($this->video->path);
        if (file_exists($videofile) && is_readable($videofile) && is_writable($videofile)) {

            $video = $ffmpeg->open(Storage::disk('uploaded')->path($this->video->path));

            $video = $this->applyFilters($video);

            $h264->on('progress', function ($video, $format, $percentage, $remaining, $rate) use ($pid, $converted_name) {
                //if (($percentage % 5) === 0) {
                    Log::info("Host: $this->host, PID: $pid, $percentage% of $converted_name transcoded, $remaining sec remaining, rate: $rate fps");
                    $this->progress = (int) $percentage;
                    $this->video->update([
                        'percentage' => $this->progress,
                    ]);
                //}
            });

            Log::debug('Executing ' . print_r($video->getFinalCommand($h264, Storage::disk('converted')->path($this->getTargetFile())), true));
            $video->save($h264, Storage::disk('converted')->path($this->getTargetFile()));
            $this->video->update([
                'converted_at' => Carbon::now(),
                'processed' => Video::PROCESSED,
                'percentage' => 100
            ]);
        }

        else {
            Log::debug("File " . $videofile . ' is not readable, please check permissions of the storage folder');
        }

	    Log::debug("Exiting " . __METHOD__);
    }

    public function createThumbnail()
    {
        Log::debug("Entering " . __METHOD__);

        $payload = $this->video->target;
        $target = $payload['thumbnail_item'];

        $key = array_key_first($target);
        $converted_name = $this->video->path . '_' . $payload['source']['created_at'] . '_' . $key . '.jpg';

        $ffmpeg = FFMpeg\FFMpeg::create(self::getFFmpegConfig());
        if(self::getFFmpegConfig()['ffmpeg.debug']) {
            $ffmpeg->getFFMpegDriver()->listen(new DebugListener());
            $ffmpeg->getFFMpegDriver()->on('debug', function ($message) {
                Log::info(__METHOD__ . ' FFmpeg: ' . $message);
            });
        }

        $videofile = Storage::disk('uploaded')->path($this->video->path);
        if (file_exists($videofile) && is_readable($videofile) && is_writable($videofile)) {
            $ffmpeg->open($videofile)
                ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds($target[$key]['second']))
                ->save(Storage::disk('converted')->path($converted_name));

            $this->video->update([
                'converted_at' => Carbon::now(),
                'processed' => Video::PROCESSED,
                'percentage' => 100,
                'file' => $converted_name,
                'host' => $this->host
            ]);

            $guzzle = new Client();

            $api_token = $this->user->api_token;
            $url = $this->user->url . self::TRANSCODERWEBSERVICE_CALLBACK;

            $response = $guzzle->post($url, [
                RequestOptions::JSON => [
                    'api_token' => $api_token,
                    'mediakey' => $this->video->mediakey,
                    'thumbnail' => [
                        'url' => route('getFile', $converted_name)
                    ]
                ]
            ]);

            Log::debug(__METHOD__ . ': ' . $response->getReasonPhrase());

            if ($this->downloadComplete() && $this->video->download()->get('processed')) {
                $this->video->download()->update(['processed' => Download::PROCESSED]);
                $this->executeFinalCallback();
            }
        }
        else {
            Log::debug("File " . $videofile . ' is not readable, please check permissions of the storage folder');
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function createSpritemap()
    {
	    Log::debug("Entering " . __METHOD__);
        $payload = $this->video->target;
        $spritemap = $payload['spritemap'];

        $converted_name = $this->video->path . '_' . $payload['source']['created_at'] . '_sprites.jpg';

        $target_width = $spritemap['width'] ?? self::SPRITEMAP_DEFAULT_WIDTH;
        $target_height = $spritemap['height'] ?? self::SPRITEMAP_DEFAULT_HEIGHT;

        $ffmpeg = FFMpeg\FFMpeg::create(self::getFFmpegConfig());
        if(self::getFFmpegConfig()['ffmpeg.debug']) {
            $ffmpeg->getFFMpegDriver()->listen(new DebugListener());
            $ffmpeg->getFFMpegDriver()->on('debug', function ($message) {
                Log::info('FFmpeg: ' . $message);
            });
        }

        $ffprobe = FFMpeg\FFProbe::create(self::getFFmpegConfig());
        if(self::getFFmpegConfig()['ffprobe.debug']) {
            $ffprobe->getFFProbeDriver()->listen(new DebugListener());
            $ffprobe->getFFProbeDriver()->on('debug', function ($message) {
                Log::info('FFprobe: ' . $message);
            });
        }

        $videofile = Storage::disk('uploaded')->path($this->video->path);
        if (file_exists($videofile) && is_readable($videofile) && is_writable($videofile)) {
            $source_format = $ffprobe
                ->streams($videofile)
                ->videos()
                ->first();

            $video = $ffmpeg->open(Storage::disk('uploaded')->path($this->video->path));
            $fps = $spritemap['count'] / ceil($source_format->get('duration'));

            $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(0))
                ->addFilter(new CustomFrameFilter('scale=' . $target_width . ':' . $target_height . ',fps=' . $fps . ',tile=10x10:margin=2:padding=2'))
                ->save(Storage::disk('converted')->path($converted_name));

            $this->video->update([
                'converted_at' => Carbon::now(),
                'processed' => Video::PROCESSED,
                'percentage' => 100,
                'file' => $converted_name,
                'host' => $this->host
            ]);

            $guzzle = new Client();

            $api_token = $this->user->api_token;
            $url = $this->user->url . self::TRANSCODERWEBSERVICE_CALLBACK;

            $response = $guzzle->post($url, [
                RequestOptions::JSON => [
                    'api_token' => $api_token,
                    'mediakey' => $this->video->mediakey,
                    'spritemap' => [
                        'count' => $spritemap['count'],
                        'url' => route('getFile', $converted_name)
                    ]
                ]
            ]);

            Log::debug(__METHOD__ . ': ' . $response->getReasonPhrase());

            if ($this->downloadComplete() && $this->video->download()->get('processed')) {
                $this->video->download()->update(['processed' => Download::PROCESSED]);
                $this->executeFinalCallback();
            }
        }
        else {
            Log::debug("File " . $videofile . ' is not readable, please check permissions of the storage folder');
        }
	    Log::debug("Exiting " . __METHOD__);
    }

    public function setPreview($preview = true)
    {
        $this->preview = $preview;
    }

    public function getPreview()
    {
        return $this->preview;
    }

    public function setHLS($hls = true)
    {
        $this->hls = $hls;
    }

    public function getHLS()
    {
        return $this->hls;
    }

    public static function getFFmpegConfig()
    {
        return array(
            'ffmpeg.binaries' => config('php-ffmpeg.ffmpeg.binaries'),
            'ffmpeg.threads' => config('php-ffmpeg.ffmpeg.threads'),
            'ffprobe.binaries' => config('php-ffmpeg.ffprobe.binaries'),
            'ffmpeg.debug' => config('php-ffmpeg.ffmpeg.debug'),
            'ffprobe.debug' => config('php-ffmpeg.ffprobe.debug'),
            'timeout' => config('php-ffmpeg.timeout'),
        );
    }

    public function executeCallback()
    {
        Log::debug("Entering " . __METHOD__);
        $guzzle = new Client();
        $api_token = $this->user->api_token;
        $url = $this->user->url . self::TRANSCODERWEBSERVICE_CALLBACK;

        if ($this->getHLS())
        {
            $archiveFile = $this->createHLSArchive();

            $requestOptions = array(
                RequestOptions::JSON => [
                    'api_token' => $api_token,
                    'mediakey' => $this->video->mediakey,
                    'medium' => [
                        'label' => $this->video->target['label'],
                        'url' => route('getFile', $archiveFile),
                        'hls' => true,
                        'vbr' => $this->video->target['vbr'],
                        'abr' => $this->video->target['abr'],
                        'size' => $this->video->target['size'],
                        'extension' => $this->video->target['extension'],
                        'created_at' => $this->video->target['created_at'],
                        'default' => $this->video->target['default'] ?? false,
                        'checksum' => md5_file(Storage::disk('converted')->path($archiveFile))
                    ]
                ]);
        }
        else {
            $ffprobe = FFMpeg\FFProbe::create();
            if(self::getFFmpegConfig()['ffprobe.debug']) {
                $ffprobe->getFFProbeDriver()->listen(new DebugListener());
                $ffprobe->getFFProbeDriver()->on('debug', function ($message) {
                    Log::info('FFprobe: ' . $message);
                });
            }

            $source_format = $ffprobe
                ->streams(Storage::disk('uploaded')->path($this->video->path))
                ->videos()
                ->first();

            $target_format = $ffprobe
                ->streams(Storage::disk('converted')->path($this->getTargetFile()))
                ->videos()
                ->first();

            $tags = $target_format->get('tags');
            $orientation = empty($tags['rotate']) ? 0 : (int) $tags['rotate'];

            $requestOptions = array(
                RequestOptions::JSON => [
                    'api_token' => $api_token,
                    'mediakey' => $this->video->mediakey,
                    'medium' => [
                        'label' => $this->video->target['label'],
                        'url' => route('getFile', $this->getTargetFile()),
                        'checksum' => md5_file(Storage::disk('converted')->path($this->getTargetFile())),
                        'default' => $this->video->target['default'] ?? false
                    ],
                    'properties' => [
                        'source_width' => $source_format->get('width'),
                        'source_height' => $source_format->get('height'),
                        'duration' => round($target_format->get('duration'), 0),
                        'filesize' => filesize(Storage::disk('converted')->path($this->getTargetFile())),
                        'width' => $target_format->get('width'),
                        'height' => $target_format->get('height'),
                        'orientation' => $orientation,
                        'vbitrate' => $target_format->get('bit_rate'),
                        'source_is360video' => $this->check360Video($source_format)
                    ]
                ]);
        }

        $response = $guzzle->post($url, $requestOptions);

        Log::debug(__METHOD__ .': '. $response->getReasonPhrase());
        if ($this->downloadComplete() && $this->video->download()->get('processed'))
        {
            $this->video->download()->update(['processed' => Download::PROCESSED]);
            $this->executeFinalCallback();
        }
        Log::debug("Exiting " . __METHOD__);
    }

    public function executeFinalCallback()
    {
	    Log::debug("Entering " . __METHOD__);
        Log::info('Executing final callback for mediakey ' . $this->video->mediakey);
        $guzzle = new Client();

        $api_token = $this->user->api_token;
        $url = $this->user->url . self::TRANSCODERWEBSERVICE_CALLBACK;

        $response = $guzzle->post($url, [
            RequestOptions::JSON => [
                'api_token' => $api_token,
                'mediakey' => $this->video->mediakey,
                'finished' => true
            ]
        ]);

        Log::debug(__METHOD__ .': '. $response->getReasonPhrase());
	    Log::debug("Exiting " . __METHOD__);
    }

    public static function executeErrorCallback($video, $message)
    {
	    Log::debug("Entering " . __METHOD__);
        Log::info('Executing error callback for mediakey ' . $video->mediakey);
        $guzzle = new Client();

        $user = User::find($video->user_id);
        $api_token = $user->api_token;
        $url = $user->url . self::TRANSCODERWEBSERVICE_CALLBACK;

        $response = $guzzle->post($url, [
            RequestOptions::JSON => [
                'api_token' => $api_token,
                'mediakey' => $video->mediakey,
                'error' => [ 'message' => $message ]
            ]
        ]);
        Log::debug(__METHOD__ .': '. $response->getReasonPhrase());
	    Log::debug("Exiting " . __METHOD__);
    }

    public function downloadComplete()
    {
	    Log::debug("Entering " . __METHOD__);
        Log::info('Check if all downloads are complete for mediakey ' . $this->video->mediakey);
        try {
            $video = Video::where('mediakey', '=', $this->video->mediakey)->firstOrFail();
            $total = Video::where('download_id', $video->download_id)->count();
            $processed = Video::where('download_id', $video->download_id)->where('processed', Video::PROCESSED)->whereNotNull('downloaded_at')->count();
            if ($total === $processed) {
                Log::info('All downloads are complete for mediakey ' . $this->video->mediakey . " ($processed of $total)");
                Log::debug("Exiting " . __METHOD__);
                return true;
            }
            Log::info('Downloads are not yet complete for mediakey ' . $this->video->mediakey . " ($processed of $total)");
            Log::debug("Exiting " . __METHOD__);
            return false;
        } catch (\Exception $exception) {
            Log::info('Downloads are incomplete for mediakey ' . $this->video->mediakey);
            Log::debug("Exiting " . __METHOD__);
            return false;
        }
    }

    protected function check360Video($source_format)
    {
        $is360Video = false;
        $side_data_list = isset($source_format->get('side_data_list')[0]) ? $source_format->get('side_data_list')[0] : null;
        if (isset($side_data_list["side_data_type"])) {
            $side_data_type = Arr::get($side_data_list, 'side_data_type');
            $is360Video = Str::contains($side_data_type, 'Spherical Mapping');
        }
        return $is360Video;
    }

    protected function applyInitialParameters()
    {
        $profile_options_db = $this->profile->options->pluck('value', 'key')->toArray();

        $profile_options = array();
        foreach ($profile_options_db as $key => $value) {
            $profile_options[] = $key;
            $profile_options[] = $value;
        }
        if ($this->preview) {
            $profile_options[] = '-ss';
            $profile_options[] = FFMpeg\Coordinate\TimeCode::fromSeconds($this->video->download()->get()->first()->payload['target']['start']);
        }

        return $profile_options;
    }

    protected function applyAdditionalParameters()
    {
        $payload = $this->video->download()->get()->first()->payload;
        $profile_additional_parameters_db = $this->profile->additionalparameters->pluck('value', 'key')->toArray();
        $profile_additional_parameters = array();
        foreach ($profile_additional_parameters_db as $key => $value) {
            $profile_additional_parameters[] = $key;
            $profile_additional_parameters[] = $value;
        }
        if ($this->getPreview()) {
            $profile_additional_parameters[] = '-t';
            $profile_additional_parameters[] = FFMpeg\Coordinate\TimeCode::fromSeconds($payload['target']['duration']);
        }
        if ($this->getHLS()) {
            $filepath_ts = Storage::disk('converted')->path(substr($this->getTargetFile(), 0, -5) . '_%03d.ts');

            $profile_additional_parameters[] = '-hls_time';
            $profile_additional_parameters[] = '4';
            $profile_additional_parameters[] = '-hls_playlist_type';
            $profile_additional_parameters[] = 'vod';
            $profile_additional_parameters[] = '-hls_segment_filename';
            $profile_additional_parameters[] = $filepath_ts;
        }
        return $profile_additional_parameters;
    }

    protected function getTargetFile()
    {
        $target = $this->video->target;
        $separator = '_';

        if (isset($target['default']) && $target['default'] == true) {
            $target['label'] = '';
            $separator = '';
        }

        $file = $this->video->path . '_' . $target['created_at'] . $separator . $target['label'] . '.' . $target['extension'];

        if($this->getHLS())
        {
            Storage::disk('converted')->makeDirectory($this->getHLSDirectory());
            $file = $this->getHLSDirectory() . DIRECTORY_SEPARATOR . $this->video->path . '_' . $target['created_at'] . $separator . $target['label'] . '_' . $target['extension'] . '.m3u8';;
        }

        if ($this->getPreview())
        {
            $file = 'preview_' . $file;
        }
        return $file;
    }

    private function applyFilters($video)
    {
        $w = $this->dimension->getWidth();
        $h = $this->dimension->getHeight();
        switch ($this->profile->encoder) {
            case 'h264_vaapi':
            {
                $scale_vaapi = 'scale_vaapi=w=\'if(gt(a\,'.$w.'/'.$h.')\,'.$w.'\,oh*a)\':h=\'if(gt(a\,'.$w.'/'.$h.')\,ow/a\,'.$h.')\'';
                $video->filters()->custom($scale_vaapi)->synchronize();
                return $video;
            }

            case 'h264_nvenc':
            {
                $scale_nvenc = 'hwupload,scale_npp=w='.$w.':h='.$h.':force_original_aspect_ratio=decrease:force_divisible_by=2:interp_algo=super';
                //$scale_nvenc = 'scale_npp=w=\'if(gt(a\,'.$w.'/'.$h.')\,'.$w.'\,oh*a)\':h=\'if(gt(a\,'.$w.'/'.$h.')\,ow/a\,'.$h.')\':interp_algo=super';
                $video->filters()->custom($scale_nvenc)->synchronize();
                return $video;
            }

            default:
            {
                $scale_default = 'scale=w='.$w.':h='.$h.':force_original_aspect_ratio=decrease,crop=\'iw-mod(iw\,2)\':\'ih-mod(ih\,2)\'';
                $video->filters()->custom($scale_default)->synchronize();
                return $video;
            }
        }
    }

    private function prepare()
    {
        if($this->getHLS())
        {
            Storage::disk('converted')->deleteDirectory($this->getHLSDirectory());
        }
    }

    private function getHLSDirectory()
    {
        return $this->video->path . '_' . $this->video->target['label'] . '_' . $this->video->target['extension'];
    }

    public static function getFFmpegVersion()
    {
        try{
            $ffmpeg = FFMpeg\FFMpeg::create(self::getFFmpegConfig());
            return $ffmpeg->getFFMpegDriver()->getVersion();
        }
        catch (Exception $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function createHLSArchive(): string
    {
        $files = Storage::disk('converted')->files($this->getHLSDirectory());
        $archiveFile = $this->getHLSDirectory() . '.zip';
        Log::info('Archive: ' . $archiveFile);

        $archive = new ZipArchive();

        if ($archive->open(Storage::disk('converted')->path($archiveFile), ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            foreach ($files as $file) {
                if ($archive->addFile(Storage::disk('converted')->path($file), basename($file))) {
                    continue;
                }
                throw new Exception("File [`{$file}`] could not be added to the zip file: " . $archive->getStatusString());
            }

            if ($archive->close()) {
                $this->video->update([
                    'file' => $archiveFile
                ]);
            }
        }
        return $archiveFile;
    }
}
