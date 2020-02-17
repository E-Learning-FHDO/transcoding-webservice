<?php

use Illuminate\Database\Seeder;

class ProfileOptionsTableSeeder extends Seeder {

    public function run()
    {
        DB::table('profile_options')->delete();

        $profile_settings = array(
            // nvenc
            array(
                'id' => 1,
                'profile_id'      => 2,
                'key'      => '-hwaccel',
                'value'      => 'cuvid',
            ),
            array(
                'id' => 2,
                'profile_id'      => 2,
                'key'      => '-c:v',
                'value'      => 'h264_cuvid',
            ),
            array(
                'id' => 3,
                'profile_id'      => 2,
                'key'      => '-vsync',
                'value'      => '0',
            ),

            //vaapi
            array(
                'id' => 4,
                'profile_id'      => 3,
                'key'      => '-hwaccel',
                'value'      => 'vaapi',
            ),
            array(
                'id' => 5,
                'profile_id'      => 3,
                'key'      => '-hwaccel_output_format',
                'value'      => 'vaapi',
            ),
            array(
                'id' => 6,
                'profile_id'      => 3,
                'key'      => '-hwaccel_device',
                'value'      => '/dev/dri/renderD128',
            )
        );

        DB::table('profile_options')->insert( $profile_settings );
    }

}
