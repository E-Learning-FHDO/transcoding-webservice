<?php

use Illuminate\Database\Seeder;

class AdminRolesTableSeeder extends Seeder
{

    public function run()
    {
        DB::table('admin_roles')->delete();


        $admin_roles = array(
            array(
                'id' => 1,
                'name' => 'Administrator',
                'slug' => 'administrator',
            ),
            array(
                'id' => 2,
                'name' => 'User',
                'slug' => 'user',
            ),
        );

        DB::table('admin_roles')->insert($admin_roles);
    }

}
