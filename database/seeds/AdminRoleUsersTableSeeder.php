<?php

use Illuminate\Database\Seeder;

class AdminRoleUsersTableSeeder extends Seeder
{

    public function run()
    {
        DB::table('admin_role_users')->delete();


        $admin_role_users = array(
            array(
                'role_id' => 1,
                'user_id' => 1,
            ),
            array(
                'role_id' => 2,
                'user_id' => 2,
            ),
        );

        DB::table('admin_role_users')->insert($admin_role_users);
    }

}
