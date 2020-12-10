<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\User;
use Illuminate\Support\Str;

class UserList extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'user:list 
                            {--email= : user email}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'List user';

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

        $email = $this->option('email') ?: null;
        if ($email) {
            $user = User::whereEmail($email)->first();
        }
        else {
            $user = User::all();
        }

        if ($user) {
            unset($user->api_token);
            $this->info($user->toJson(JSON_PRETTY_PRINT));
        }

        Log::debug("Exiting " . __METHOD__);
    }
}
