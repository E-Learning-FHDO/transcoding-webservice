<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Str;

class UserCreate extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'user:create 
                            {--email= : user email} 
                            {--name= : user name} 
                            {--api_token= : set API token} 
                            {--profile_id= : set encoder profile} 
                            {--url= : set URL } 
                            {--role_ids= : assign roles} 
                            {--password= : set user password} 
                            {--show : show output als JSON string}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Create user';

    /**
     * Create a new command instance.
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * @return mixed
     */
    public function handle()
    {
        Log::debug("Entering " . __METHOD__);

        $password = $this->option('password') ?: Str::random(32);
        $api_token = $this->option('api_token') ?: Str::random(32);
        $profile_id = $this->option('profile_id') ?: 1;
        $url = $this->option('url') ?: '';
        $role_ids = $this->option('role_ids') ? explode(',', $this->option('role_ids')) : '';

        $email = !empty($this->option('email')) ? $this->option('email') : null;
        $name = !empty($this->option('name')) ? $this->option('name') : null;

        if ($this->checkEmailIsUnique($email) && ($this->checkEmailIsValid($email)) && $this->checkNameVaild($name) && $this->checkRoleIds($role_ids)) {
            $user = new User();
            $user->password = Hash::make($password);
            $user->email = $email;
            $user->name = $name;
            $user->api_token = $api_token;
            $user->url = $url;
            $user->profile_id = $profile_id;
            $user->save();

            if (is_array($role_ids)) {
                foreach ($role_ids as $role_id) {
                    $this->assignUser($user->id, $role_id);
                    $user->roles = $role_ids;
                }
            }

            if ($this->option('show')) {
                $user->pass = $password;
                $this->info($user->toJson(JSON_PRETTY_PRINT));
            }
        }

        Log::debug("Exiting " . __METHOD__);
    }


    /**
     * @param $email
     * @return bool
     */
    public function checkEmailIsUnique($email)
    {
        if ($existingUser = User::whereEmail($email)->first()) {
            $this->error('Sorry, "' . $existingUser->email . '" is already in use by ' . $existingUser->name . '!');
            return false;
        }

        return true;
    }

    /**
     * @param $email
     * @return bool
     */
    protected function checkEmailIsValid($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Sorry, "' . $email . '" is not a valid email address!');
            return false;
        }

        return true;
    }

    /**
     * @param $name
     * @return bool
     */
    protected function checkNameVaild($name)
    {
        if (!filter_var(
            $name,
            FILTER_VALIDATE_REGEXP,
            array(
                "options" => array("regexp"=>"/[a-zA-Z\s]+/")
            )
        )) {
            $this->error('Sorry, "' . $name . '" is not a valid name, which should at least contain alpha or spaces, at least 1 char!');
            return false;
        }

        return true;
    }

    /**
     * @param $role_ids
     * @return bool
     */
    public function checkRoleIds($role_ids)
    {
        if (is_array($role_ids)) {
            foreach ($role_ids as $role_id) {
                $existingRoleId = DB::table('admin_roles')->where('id', '=', $role_id)->first();
                if (!$existingRoleId) {
                    $this->error('Sorry, role_id "' . $role_id . '" does not exist!');
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param $role_id
     * @return bool
     */
    public function assignUser($user_id, $role_id)
    {
        $existing = DB::table('admin_role_users')->where('user_id','=',$user_id)->where('role_id', '=', $role_id)->first();
        if (!$existing) {
            $role_users = array(
                array(
                    'user_id' => $user_id,
                    'role_id' => $role_id
                )
            );
            DB::table('admin_role_users')->insert($role_users);
        }
    }
}
