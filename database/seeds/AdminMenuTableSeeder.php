<?php

use Illuminate\Database\Seeder;

class AdminMenuTableSeeder extends Seeder
{

    public function run()
    {
        DB::table('admin_menu')->delete();


        $admin_menu = array(
            array(
                'id' => 1,
                'title' => 'Dashboard',
                'icon' => 'fa-bar-chart',
                'permission' => 'dashboard',
                'parent_id' => 0,
                'order' => 1,
                'uri' => '/',
            ),
            array(
                'id' => 2,
                'title' => 'Admin',
                'icon' => 'fa-tasks',
                'permission' => 'auth.management',
                'parent_id' => 0,
                'order' => 2,
                'uri' => '',
            ),
            array(
                'id' => 3,
                'title' => 'Users',
                'icon' => 'fa-users',
                'permission' => null,
                'parent_id' => 2,
                'order' => 3,
                'uri' => 'users',
            ),
            array(
                'id' => 4,
                'title' => 'Roles',
                'icon' => 'fa-user',
                'permission' => null,
                'parent_id' => 2,
                'order' => 4,
                'uri' => 'auth/roles',
            ),
            array(
                'id' => 5,
                'title' => 'Permission',
                'icon' => 'fa-ban',
                'permission' => null,
                'parent_id' => 2,
                'order' => 5,
                'uri' => 'auth/permissions',
            ),
            array(
                'id' => 6,
                'title' => 'Menu',
                'icon' => 'fa-bars',
                'permission' => null,
                'parent_id' => 2,
                'order' => 6,
                'uri' => 'auth/menu',
            ),
            array(
                'id' => 7,
                'title' => 'Operation log',
                'icon' => 'fa-history',
                'permission' => null,
                'parent_id' => 2,
                'order' => 7,
                'uri' => 'auth/logs',
            ),
            array(
                'id' => 8,
                'title' => 'Helpers',
                'icon' => 'fa-gears',
                'permission' => 'ext.helpers',
                'parent_id' => 0,
                'order' => 8,
                'uri' => null,
            ),
            array(
                'id' => 9,
                'title' => 'Scaffold',
                'icon' => 'fa-keyboard-o',
                'permission' => null,
                'parent_id' => 8,
                'order' => 9,
                'uri' => 'helpers/scaffold',
            ),
            array(
                'id' => 10,
                'title' => 'Database terminal',
                'icon' => 'fa-database',
                'permission' => null,
                'parent_id' => 8,
                'order' => 10,
                'uri' => 'helpers/terminal/database',
            ),
            array(
                'id' => 11,
                'title' => 'Laravel artisan',
                'icon' => 'fa-terminal',
                'permission' => null,
                'parent_id' => 8,
                'order' => 11,
                'uri' => 'helpers/terminal/artisan',
            ),
            array(
                'id' => 12,
                'title' => 'Routes',
                'icon' => 'fa-list-alt',
                'permission' => null,
                'parent_id' => 8,
                'order' => 12,
                'uri' => 'helpers/routes',
            ),
            array(
                'id' => 13,
                'title' => 'Profiles',
                'icon' => 'fa-bars',
                'permission' => 'profile.management',
                'parent_id' => 0,
                'order' => 13,
                'uri' => '/profiles',
            ),
            array(
                'id' => 14,
                'title' => 'Download Queue',
                'icon' => 'fa-arrow-down',
                'permission' => null,
                'parent_id' => 0,
                'order' => 14,
                'uri' => '/downloadqueue',
            ),
            array(
                'id' => 15,
                'title' => 'Transcoding Queue',
                'icon' => 'fa-youtube-play',
                'permission' => null,
                'parent_id' => 0,
                'order' => 15,
                'uri' => '/transcodingqueue',
            ),
            array(
                'id' => 16,
                'title' => 'Laravel logs',
                'icon' => 'fa-youtube-play',
                'permission' => 'ext.helpers',
                'parent_id' => 8,
                'order' => 15,
                'uri' => '/logs',
            )
        );

        DB::table('admin_menu')->insert($admin_menu);
    }

}
