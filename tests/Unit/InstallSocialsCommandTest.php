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
})->with([
    'not a number' => 'not-a-number',
    'zero' => '0',
    'negative' => '-1',
    'decimal' => '1.5',
    'leading whitespace' => ' 1',
    'overflow' => ((string) PHP_INT_MAX) . '0',
]);
