<?php

declare(strict_types=1);

use Capell\Socials\Console\Commands\InstallSocialsCommand;
use Symfony\Component\Console\Tester\CommandTester;

it('fails closed before installation when the site option is not a positive integer', function (string $site): void {
    $command = new InstallSocialsCommand;
    $command->setLaravel(app());
    $tester = new CommandTester($command);

    $exitCode = $tester->execute(['--site' => $site]);

    expect($exitCode)->toBe(2)
        ->and($tester->getDisplay())->toContain('The --site option must be a positive integer site ID.');
})->with(['not-a-number', '0', '-1', '1.5']);
