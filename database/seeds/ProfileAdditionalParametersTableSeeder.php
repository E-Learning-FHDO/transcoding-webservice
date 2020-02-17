<?php

use Illuminate\Database\Seeder;

class ProfileAdditionalParametersTableSeeder extends Seeder {

    public function run()
    {
        DB::table('profile_additional_parameters')->delete();

        $profile_additional_parameters = array(
            // libx264
            array(
                'id' => 1,
                'profile_id'      => 1,
                'key'      => '-pix_fmt',
                'value'      => 'yuv420p',
            ),
            array(
                'id' => 2,
                'profile_id'      => 1,
                'key'      => '-ac',
                'value'      => '2',
            ),

            // nvenc
            array(
                'id' => 3,
                'profile_id'      => 2,
                'key'      => '-ac',
                'value'      => '2',
            ),

            // vaapi
            array(
                'id' => 4,
                'profile_id'      => 3,
                'key'      => '-ac',
                'value'      => '2',
            ),
        );

        DB::table('profile_additional_parameters')->insert( $profile_additional_parameters );
    }

}
