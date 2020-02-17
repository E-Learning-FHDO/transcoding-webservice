<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UsersTableSeeder extends Seeder {

    public function run()
    {
        DB::table('users')->delete();


        $users = array(
            array(
                'id' => 1,
                'name'      => 'admin',
                'email'      => 'admin@example.org',
                'password'   => Hash::make('admin'),
		        'api_token'   => Str::random(32),
                'url'  => '',
                'profile_id' => 1
            ),
            array(
                'id' => 2,
                'name'      => 'user',
                'email'      => 'user@example.org',
                'password'   => Hash::make('user'),
		        'api_token'  => Str::random(32),
                'url'  => '',
                'profile_id' => 1
            )
        );

        DB::table('users')->insert( $users );
    }

}
