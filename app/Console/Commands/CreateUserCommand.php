<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User; // Import your User model
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUserCommand extends Command
{
    protected $signature = 'user:create {username} {password}';
    protected $description = 'Create a new user';

    public function handle()
    {
        $username = $this->argument('username');
        $password = $this->argument('password');

        $user = new User();
        $user->username = $username;
        $user->password = Hash::make($password);
        $user->api_token = Str::random(80);
        // Set any other default values for new users if necessary

        $user->save();

        $this->info('User created successfully.');
    }
}
