<?php

namespace App\Jobs;

use App\User;
use Encore\Admin\Facades\Admin;
use FFMpeg;
use App\Models\Video;
use App\Format\Video\H264;
use Carbon\Carbon;
use FFMpeg\Coordinate\Dimension;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConvertVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    private $dimension;

    public function __construct(Video $video)
    {
        $this->video = $video;
        $target = $this->video->target;

        $size = explode('x', $target['size']);
        $this->dimension = new Dimension($size[0], $size[1]);
    }

    public function handle()
    {
        $target = $this->video->target;
        $separator = '_';

        if(isset($target['default']) && $target['default'] == true)
        {
            $target['label'] = '';
            $separator = '';
        }

        $profile_id = DB::table('users')->where('id','=', $this->video->uid)->pluck('profile_id')->first();
        $profile = DB::table('profiles')->where('id','=', $profile_id)->pluck('encoder')->first();
        $fallback_id = DB::table('profiles')->where('id','=', $profile_id)->pluck('fallback_id')->first();
        $fallback = DB::table('profiles')->where('id','=', $fallback_id)->pluck('encoder')->first();

        $converted_name = $this->video->path . '_' . $target['created_at'] . $separator . $target['label'] . '.' . $target['format'];

        $ffprobe = FFMpeg\FFProbe::create();

        $source_format = $ffprobe
            ->streams(Storage::disk('uploaded')->path($this->video->path)) // extracts streams information
            ->videos()
            ->first();

        $is360Video = $this->check360Video($source_format);

        if($this->attempts() > 1 && isset($fallback))
        {
            echo Carbon::now()->toDateTimeString() . " Failed to encode $converted_name with $profile codec\n";
            $profile_id = $fallback_id;
            $profile = $fallback;
        }

        $h264 = (new H264('aac', $profile))
            ->setKiloBitrate($target['vbr'])
            ->setAudioKiloBitrate($target['abr']);


        $profile_options_db = DB::table('profile_options')->select('key','value')->where('profile_id','=', $profile_id)->pluck('value', 'key')->toArray();

        $profile_options = array();
        foreach ($profile_options_db as $key => $value)
        {
            $profile_options[] = $key;
            $profile_options[] = $value;
        }

        $profile_additional_parameters_db = DB::table('profile_additional_parameters')->select('key','value')->where('profile_id','=', $profile_id)->pluck('value', 'key')->toArray();

        $profile_additional_parameters = array();
        foreach ($profile_additional_parameters_db as $key => $value)
        {
            $profile_additional_parameters[] = $key;
            $profile_additional_parameters[] = $value;
        }

        $h264->setAdditionalParameters($profile_additional_parameters);

        $h264->on('progress', function ($video, $format, $percentage) use ($converted_name) {
            if(($percentage % 5) == 0)
            {
                $dt = Carbon::now()->toDateTimeString();
                echo "$dt : $percentage% of $converted_name transcoded\n";
            }
        });

        $ffmpeg = FFMpeg\FFMpeg::create();

        $video = $ffmpeg->open(Storage::disk('uploaded')->path($this->video->path));
        echo Carbon::now()->toDateTimeString() . " Trying to encode $converted_name with $profile codec ..\n";

        switch($profile)
        {
            case 'h264_vaapi':
            {
                $video->setOptions($profile_options);
                $video->filters()->custom('scale_vaapi=' . $this->dimension->getWidth() . ':' . $this->dimension->getHeight())->synchronize();
                break;
            }

            case 'h264_nvenc':
            {
                $video->setOptions($profile_options);
                $video->filters()->custom('scale_npp=' . $this->dimension->getWidth() . ':' . $this->dimension->getHeight() . ':interp_algo=super')->synchronize();
                break;
            }

            default:
            {
                $video->filters()->resize($this->dimension)->synchronize();
            }
        }

        $video->save($h264, Storage::disk('converted')->path($converted_name));

        if($is360Video)
        {
            $video->filters()->addMetadata(['side_data_list' => $source_format->get('side_data_list')])->synchronize();
        }

        $this->video->update([
            'converted_at' => Carbon::now(),
            'processed' => true,
            'file' => $converted_name
        ]);

        $target_format = $ffprobe
            ->streams(Storage::disk('converted')->path($converted_name)) // extracts streams information
            ->videos()
            ->first();

        $guzzle = new Client();

        $api_token = DB::table('users')->where('id', $this->video->uid)->pluck('api_token')->first();

        try {
            $url = DB::table('users')->where('api_token', $api_token)->pluck('url')->first() . '/transcoderwebservice/callback';
        }
        catch (ModelNotFoundException $e) {
            echo 'No entry found for api key ';
        }

        $response = $guzzle->post($url, [
            RequestOptions::JSON => [
                'api_token' => $api_token,
                'mediakey' => $this->video->mediakey,
                'medium' => [
                    'label' => $target['label'],
                    'url' =>  route('getFile', $converted_name)
                ],
                'properties' => [
                    'source_width' => $source_format->get('width'),
                    'source_height' => $source_format->get('width'),
                    'duration' => round($target_format->get('duration'), 0),
                    'filesize' => $target_format->get('filesize'),
                    'width' => $target_format->get('width'),
                    'height' => $target_format->get('height'),
                    'is360video' => $is360Video
                ]
            ]
        ]);

    }

    public function failed($exception)
    {
       echo $exception->getMessage();
    }

    public function jobs()
    {
        return $this->onQueue();
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
}
