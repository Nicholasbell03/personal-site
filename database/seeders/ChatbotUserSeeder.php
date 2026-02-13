<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChatbotUserSeeder extends Seeder
{
    /**
     * Seed the dedicated chatbot user for anonymous conversations.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => config('chat.user.email')],
            [
                'name' => config('chat.user.name'),
                'password' => bcrypt(Str::random(64)),
            ],
        );
    }
}
