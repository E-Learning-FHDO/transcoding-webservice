<?php

namespace App\Http\Controllers;

use App\Format\Video\H264;
use App\Models\Profile;
use App\Models\Video;
use App\User;
use Carbon\Carbon;
use FFMpeg\Filters\Frame\CustomFrameFilter;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use FFMpeg;
use FFMpeg\Coordinate\Dimension;

class TranscodingController extends Controller
{

    public $video;
    private $dimension;
    private $preview;
    private $user;
    private $profile;
    private $attempts;

    public function __construct(Video $video, Dimension $dimension, $attempts)
    {
        $this->video = $video;
        $this->dimension = $dimension;
        $this->attempts = $attempts;
    }

    public function transcode()
    {
        $target = $this->video->target;

        $this->user = User::find($this->video->user_id);
        $this->profile = $this->user->profile;

        $converted_name = $this->getTargetFile();

        $ffprobe = FFMpeg\FFProbe::create($this->getFFmpegConfig());

        $source_format = $ffprobe
            ->streams(Storage::disk('uploaded')->path($this->video->path))
            ->videos()
            ->first();

        $is360Video = $this->check360Video($source_format);

        if ($this->attempts > 1) {
            echo Carbon::now()->toDateTimeString() . " Failed to encode clip $converted_name with " . $this->profile->encoder . " codec\n";
            $this->profile = Profile::find($this->user->profile->fallback_id);
        }

        $h264 = (new H264('aac', $this->profile->encoder))
            ->setKiloBitrate($target['vbr'])
            ->setAudioKiloBitrate($target['abr']);


        $h264->setAdditionalParameters($this->applyAdditionalParameters());

        $ffmpeg = FFMpeg\FFMpeg::create($this->getFFmpegConfig());

        $video = $ffmpeg->open(Storage::disk('uploaded')->path($this->video->path));

        $video = $this->applyFilters($video);

        $h264->setInitialParameters($this->applyInitialParameters());

        echo Carbon::now()->toDateTimeString() . " Trying to encode clip $converted_name with " . $this->profile->encoder . " codec ..\n";

        $video->save($h264, Storage::disk('converted')->path($this->getTargetFile()));

        if ($is360Video) {
            $video->filters()->addMetadata(['side_data_list' => $source_format->get('side_data_list')])->synchronize();
        }

        $h264->on('progress', function ($video, $format, $percentage) use ($converted_name) {
            if (($percentage % 5) == 0) {
                $dt = Carbon::now()->toDateTimeString();
                echo "$dt : $percentage% of $converted_name transcoded\n";
                $this->progress = $percentage;
            }
        });

        $this->video->update([
            'converted_at' => Carbon::now(),
            'processed' => true,
            'file' => $this->getTargetFile()
        ]);

        $this->executeCallback();
    }

    public function createThumbnail()
    {
        $payload = $this->video->target;
        $target = $payload['thumbnail_item'];
        $user = User::find($this->video->user_id);

        $key = array_key_first($target);
        $converted_name = $this->video->path . '_' . $payload['source']['created_at'] . '_' . $key . '.jpg';

        $ffmpeg = FFMpeg\FFMpeg::create($this->getFFmpegConfig());

        $ffmpeg->open(Storage::disk('uploaded')->path($this->video->path))
            ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds($target[$key]['second']))
            ->save(Storage::disk('converted')->path($converted_name));

        $this->video->update([
            'converted_at' => Carbon::now(),
            'processed' => true,
            'file' => $converted_name
        ]);

        $guzzle = new Client();

        $api_token = $user->api_token;
        $url = $user->url . '/transcoderwebservice/callback';

        $response = $guzzle->post($url, [
            RequestOptions::JSON => [
                'api_token' => $api_token,
                'mediakey' => $this->video->mediakey,
                'thumbnail' => [
                    'url' => route('getFile', $converted_name)
                ]
            ]
        ]);
    }

    public function createSpritemap()
    {
        $payload = $this->video->target;
        $spritemap = $payload['spritemap'];

        $user = User::find($this->video->user_id);

        $converted_name = $this->video->path . '_' . $payload['source']['created_at'] . '_sprites.jpg';

        $target_width = isset($spritemap['width']) ? $spritemap['width'] : 142;
        $target_height = isset($spritemap['height']) ? $spritemap['height'] : 80;

        $ffmpeg = FFMpeg\FFMpeg::create($this->getFFmpegConfig());
        $ffprobe = FFMpeg\FFProbe::create($this->getFFmpegConfig());

        $source_format = $ffprobe
            ->streams(Storage::disk('uploaded')->path($this->video->path))
            ->videos()
            ->first();

        $video = $ffmpeg->open(Storage::disk('uploaded')->path($this->video->path));
        $fps = $spritemap['count'] / ceil($source_format->get('duration'));

        $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(0))
            ->addFilter(new CustomFrameFilter('scale=' . $target_width . ':' . $target_height . ',fps=' . $fps . ',tile=10x10:margin=2:padding=2'))
            ->save(Storage::disk('converted')->path($converted_name));


        $this->video->update([
            'converted_at' => Carbon::now(),
            'processed' => true,
            'file' => $converted_name
        ]);

        $guzzle = new Client();

        $api_token = $user->api_token;
        $url = $user->url . '/transcoderwebservice/callback';

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
    }

    public function setPreview($preview = true)
    {
        $this->preview = $preview;
    }

    public function getPreview()
    {
        return $this->preview;
    }

    public function getFFmpegConfig()
    {
        return array(
            'ffmpeg.binaries' => config('php-ffmpeg.ffmpeg.binaries'),
            'ffmpeg.threads' => config('php-ffmpeg.ffmpeg.threads'),
            'ffprobe.binaries' => config('php-ffmpeg.ffprobe.binaries'),
            'timeout' => config('php-ffmpeg.timeout'),
        );
    }

    protected function executeCallback()
    {
        $ffprobe = FFMpeg\FFProbe::create();

        $source_format = $ffprobe
            ->streams(Storage::disk('uploaded')->path($this->video->path))
            ->videos()
            ->first();

        $target_format = $ffprobe
            ->streams(Storage::disk('converted')->path($this->getTargetFile()))
            ->videos()
            ->first();

        $guzzle = new Client();

        $api_token = $this->user->api_token;

        $url = $this->user->url . '/transcoderwebservice/callback';

        $response = $guzzle->post($url, [
            RequestOptions::JSON => [
                'api_token' => $api_token,
                'mediakey' => $this->video->mediakey,
                'medium' => [
                    'label' => $this->video->target['label'],
                    'url' => route('getFile', $this->getTargetFile())
                ],
                'properties' => [
                    'source_width' => $source_format->get('width'),
                    'source_height' => $source_format->get('width'),
                    'duration' => round($target_format->get('duration'), 0),
                    'filesize' => $target_format->get('filesize'),
                    'width' => $target_format->get('width'),
                    'height' => $target_format->get('height'),
                    'is360video' => $this->check360Video($source_format)
                ]
            ]
        ]);

        if($this->downloadComplete())
        {
            $this->executeFInalCallback();
        }
    }

    public function executeFInalCallback()
    {
        Log::info('Executing final callback for mediakey '. $this->video->mediakey);
        $guzzle = new Client();

        $api_token = $this->user->api_token;
        $url = $this->user->url . '/transcoderwebservice/callback';

        $response = $guzzle->post($url, [
            RequestOptions::JSON => [
                'api_token' => $api_token,
                'mediakey' => $this->video->mediakey,
                'finished' => true
            ]
        ]);
    }

    public function downloadComplete()
    {
        Log::info('Check if all downloads are complete for mediakey '. $this->video->mediakey);
        try
        {
            $video = Video::where('mediakey','=', $this->video->mediakey)->firstOrFail();
            $total = Video::where('download_id', $video->download_id)->count();
            $processed = Video::where('download_id', $video->download_id)->where('processed', 1)->whereNotNull('downloaded_at')->count();
            if($total === $processed)
            {
                Log::info('All downloads are complete for mediakey '. $this->video->mediakey);
                return true;
            }
            Log::info('Downloads are not yet complete for mediakey '. $this->video->mediakey);
            return false;
        }

        catch(\Exception $exception)
        {
            Log::info('Downloads are incomplete for mediakey '. $this->video->mediakey);
            return false;
        }
    }

    protected function check360Video($source_format)
    {
        $is360Video = false;
        $side_data_list = $source_format->get('side_data_list')[0];
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
        $profile_additional_parameters_db = $this->profile->additionalparameters->pluck('value', 'key')->toArray();
        $profile_additional_parameters = array();
        foreach ($profile_additional_parameters_db as $key => $value) {
            $profile_additional_parameters[] = $key;
            $profile_additional_parameters[] = $value;
        }
        if ($this->preview) {
            $profile_additional_parameters[] = '-t';
            $profile_additional_parameters[] = FFMpeg\Coordinate\TimeCode::fromSeconds($this->video->download()->get()->first()->payload['target']['duration']);
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

        if ($this->preview) {
            return 'preview_' . $this->video->path . '_' . $target['created_at'] . $separator . $target['label'] . '.' . $target['extension'];
        }
        return $this->video->path . '_' . $target['created_at'] . $separator . $target['label'] . '.' . $target['extension'];
    }

    private function applyFilters($video)
    {
        switch ($this->profile->encoder) {
            case 'h264_vaapi':
            {
                $video->filters()->custom('scale_vaapi=' . $this->dimension->getWidth() . ':' . $this->dimension->getHeight())->synchronize();
                return $video;
            }

            case 'h264_nvenc':
            {
                $video->filters()->custom('scale_npp=' . $this->dimension->getWidth() . ':' . $this->dimension->getHeight() . ':interp_algo=super')->synchronize();
                return $video;
            }

            default:
            {
                $video->filters()->resize($this->dimension)->synchronize();
                return $video;
            }
        }
    }
}
