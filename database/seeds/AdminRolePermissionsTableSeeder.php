<?php

use Illuminate\Database\Seeder;

class AdminRolePermissionsTableSeeder extends Seeder
{

    public function run()
    {
        DB::table('admin_role_permissions')->delete();


        $admin_role_permissions = array(
            array(
                'role_id' => 1,
                'permission_id' => 1,
            ),
            array(
                'role_id' => 2,
                'permission_id' => 2,
            ),
            array(
                'role_id' => 2,
                'permission_id' => 4,
            ),
            array(
                'role_id' => 2,
                'permission_id' => 10,
            ),
            array(
                'role_id' => 2,
                'permission_id' => 11,
            ),
        );

        DB::table('admin_role_permissions')->insert($admin_role_permissions);
    }

}
