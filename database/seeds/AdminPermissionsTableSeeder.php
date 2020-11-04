<?php

use Illuminate\Database\Seeder;

class AdminPermissionsTableSeeder extends Seeder
{

    public function run()
    {
        DB::table('admin_permissions')->delete();


        $admin_permissions = array(
            array(
                'id' => 1,
                'name' => 'All permission',
                'slug' => '*',
                'http_method' => '',
                'http_path' => '*',
            ),
            array(
                'id' => 2,
                'name' => 'Dashboard',
                'slug' => 'dashboard',
                'http_method' => 'GET',
                'http_path' => '/',
            ),
            array(
                'id' => 3,
                'name' => 'Login',
                'slug' => 'auth.login',
                'http_method' => '',
                'http_path' => "/auth/login\n/auth/logout",
            ),
            array(
                'id' => 4,
                'name' => 'User setting',
                'slug' => 'auth.setting',
                'http_method' => 'GET,PUT',
                'http_path' => '/auth/setting',
            ),
            array(
                'id' => 5,
                'name' => 'Auth management',
                'slug' => 'auth.management',
                'http_method' => '',
                'http_path' => "/auth/roles\n/auth/permissions\n/auth/menu\n/auth/logs",
            ),
            array(
                'id' => 6,
                'name' => 'Logs',
                'slug' => 'ext.log-viewer',
                'http_method' => '',
                'http_path' => "/logs*",
            ),
            array(
                'id' => 7,
                'name' => 'Admin helpers',
                'slug' => 'ext.helpers',
                'http_method' => '',
                'http_path' => "/helpers/*",
            ),
            array(
                'id' => 8,
                'name' => 'Admin messages',
                'slug' => 'ext.messages',
                'http_method' => '',
                'http_path' => "/messages*",
            ),
            array(
                'id' => 9,
                'name' => 'Profile Management',
                'slug' => 'profile.management',
                'http_method' => '',
                'http_path' => "/profile*",
            ),
            array(
                'id' => 10,
                'name' => 'Download Queue',
                'slug' => 'download.queue',
                'http_method' => '',
                'http_path' => "/downloadqueue*",
            ),
            array(
                'id' => 11,
                'name' => 'Transcoding Queue',
                'slug' => 'transcoding.queue',
                'http_method' => '',
                'http_path' => "/transcodingqueue*",
            ),
            array(
                'id' => 12,
                'name' => 'Scheduling',
                'slug' => 'ext.scheduling',
                'http_method' => '',
                'http_path' => "/scheduling*",
            ),
        );

        DB::table('admin_permissions')->insert($admin_permissions);
    }

}
