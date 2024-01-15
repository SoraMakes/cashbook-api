<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUserCommand extends Command
{
    protected $signature = 'user:create {username} {password} {token?}';
    protected $description = 'Create a new user';

    public function handle()
    {
        $username = $this->argument('username');
        $password = $this->argument('password');
        $api_token = $this->argument('token') ?: Str::random(80);

        $user = new User();
        $user->username = $username;
        $user->password = Hash::make($password);
        $user->api_token = $api_token;
        // Set any other default values for new users if necessary

        $user->save();

        $this->info('User created successfully.');
    }
}
