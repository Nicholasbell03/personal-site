<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateApiToken extends Command
{
    protected $signature = 'app:generate-api-token
                            {--user= : User ID or email address}
                            {--name= : Token label (e.g. shares-extension)}';

    protected $description = 'Generate a Sanctum API token for a user';

    public function handle(): int
    {
        $userIdentifier = $this->option('user');
        $tokenName = $this->option('name');

        if (! $userIdentifier || ! $tokenName) {
            $this->error('Both --user and --name options are required.');

            return Command::FAILURE;
        }

        $user = is_numeric($userIdentifier)
            ? User::find($userIdentifier)
            : User::where('email', $userIdentifier)->first();

        if (! $user) {
            $this->error("User not found: {$userIdentifier}");

            return Command::FAILURE;
        }

        $token = $user->createToken($tokenName);

        $this->info("Token created for {$user->name} ({$user->email})");
        $this->newLine();
        $this->line("Token name: {$tokenName}");
        $this->line("Plain text token (save this — it won't be shown again):");
        $this->newLine();
        $this->line($token->plainTextToken);

        return Command::SUCCESS;
    }
}
