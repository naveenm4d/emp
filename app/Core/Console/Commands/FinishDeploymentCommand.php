<?php

namespace App\Core\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs after new code is uploaded (the deploy workflow calls it through
 * /webhooks/deploy/finish; on the server it can also be run by hand). Stops at
 * the first failing step, leaving maintenance mode on.
 */
#[Signature('app:finish-deployment')]
#[Description('Migrate, rebuild caches, restart queue workers and leave maintenance mode')]
class FinishDeploymentCommand extends Command
{
    public function handle(): int
    {
        $steps = [
            ['migrate', ['--force' => true]],
            ...(file_exists(public_path('storage')) ? [] : [['storage:link', []]]),
            ['optimize:clear', []],
            ['optimize', []],
            ['queue:restart', []],
            ['up', []],
        ];

        foreach ($steps as [$command, $arguments]) {
            if ($this->call($command, $arguments) !== self::SUCCESS) {
                $this->components->error("Deployment stopped: {$command} failed.");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
