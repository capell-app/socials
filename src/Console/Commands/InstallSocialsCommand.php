<?php

declare(strict_types=1);

namespace Capell\Socials\Console\Commands;

use Capell\Core\Actions\Install\PublishPackageMigrationsAction;
use Capell\Core\Actions\Install\RunMigrationsAction;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Install\ConsoleProgressReporter;
use Capell\Socials\Actions\ImportLegacySocialProfilesAction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

final class InstallSocialsCommand extends Command
{
    protected $signature = 'capell:socials-install {--site= : Import one site ID} {--dry-run : Report legacy imports without writing profiles}';

    protected $description = 'Install Socials tables and import legacy social profiles.';

    public function handle(ImportLegacySocialProfilesAction $importLegacySocialProfiles): int
    {
        $reporter = new ConsoleProgressReporter($this);
        $package = CapellCore::getPackage('capell-app/socials');

        PublishPackageMigrationsAction::run(new Collection([$package->name => $package]), $reporter, true, false);
        RunMigrationsAction::run($reporter);

        $site = $this->option('site');
        $siteId = is_numeric($site) ? (int) $site : null;
        $result = $importLegacySocialProfiles->handle($siteId, (bool) $this->option('dry-run'));

        $this->info(sprintf('%d social profiles imported across %d site(s).', $result->profilesImported, $result->sitesConsidered));

        foreach ($result->skipped as $message) {
            $this->warn($message);
        }

        return self::SUCCESS;
    }
}
