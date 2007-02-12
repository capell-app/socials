<?php

declare(strict_types=1);

use Capell\Socials\Filament\BuilderBlocks\SocialsBuilderBlock;
use Capell\Socials\Providers\SocialsServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

it('loads labels before block discovery can warm an empty translation group', function (): void {
    $loader = new FileLoader(new Filesystem, []);
    $this->app->instance('translation.loader', $loader);
    $this->app->instance('translator', new Translator($loader, 'en'));

    $provider = new SocialsServiceProvider($this->app);
    $provider->register();

    $block = SocialsBuilderBlock::make();
    $provider->boot();

    expect($block->getLabel())->toBe('Socials')
        ->and(__('capell-socials::socials.admin.title'))->toBe('Social profiles and sharing')
        ->and(__('capell-socials::socials.networks.linkedin'))->toBe('LinkedIn');
});
