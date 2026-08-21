<?php

declare(strict_types=1);

namespace Capell\Socials\Console\Commands;

use Capell\Socials\Actions\SeedSocialsScreenshotFixtureAction;
use Illuminate\Console\Command;
use Throwable;

final class SeedSocialsScreenshotFixtureCommand extends Command
{
    protected $signature = 'capell:socials-screenshot-fixture {--force : Confirm an intentional disposable screenshot seed}';

    protected $description = 'Seed Socials record state for an explicit disposable screenshot run';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to seed screenshot fixtures without --force.');

            return self::FAILURE;
        }

        try {
            SeedSocialsScreenshotFixtureAction::run();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Socials screenshot fixture initialized.');

        return self::SUCCESS;
    }
}
