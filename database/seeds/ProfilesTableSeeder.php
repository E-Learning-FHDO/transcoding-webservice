<?php

use Illuminate\Database\Seeder;

class ProfilesTableSeeder extends Seeder {

    public function run()
    {
        DB::table('profiles')->delete();


        $profiles = array(
            array(
                'id' => 1,
                'encoder'      => 'libx264',
                'fallback_id'      => null,
            ),
            array(
                'id' => 2,
                'encoder'      => 'h264_nvenc',
                'fallback_id'      => 1,
            ),
            array(
                'id' => 3,
                'encoder'      => 'h264_vaapi',
                'fallback_id'      => 1,
            )
        );

        DB::table('profiles')->insert( $profiles );
    }

}
