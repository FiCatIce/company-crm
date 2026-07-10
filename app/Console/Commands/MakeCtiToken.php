<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeCtiToken extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cti:make-token {--name=cti-ingest : Label for the issued token}';

    /**
     * @var string
     */
    protected $description = 'Issue a Sanctum bearer token (ability cti:ingest) to the CTI integration user.';

    public function handle(): int
    {
        // A dedicated principal for the PBX/connector — NOT a CRM staff account,
        // so it carries no role. The token's cti:ingest ability is its only power.
        $user = User::firstOrCreate(
            ['email' => 'cti@system.local'],
            ['name' => 'CTI Integration', 'password' => Str::random(40)],
        );

        $name = (string) $this->option('name');
        $token = $user->createToken($name, ['cti:ingest']);

        $this->info('CTI integration user: '.$user->email.' (id '.$user->id.')');
        $this->info('Token name: '.$name);
        $this->newLine();
        $this->line('Bearer token (shown once — store it in the connector config):');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
