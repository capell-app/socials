<?php

declare(strict_types=1);

use Capell\Socials\Data\SocialFollowRenderData;
use Capell\Socials\Data\SocialProfileData;
use Capell\Socials\Enums\SocialLabelStyle;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

it('renders escaped public links without authoring or package internals', function (): void {
    $renderData = new SocialFollowRenderData(
        heading: '<script>window.leak = true</script>Follow us',
        labelStyle: SocialLabelStyle::IconsAndLabels,
        openInNewTab: true,
        alignment: 'center',
        profiles: [
            new SocialProfileData(
                networkKey: 'custom',
                label: '<img src=x onerror=alert(1)>Community',
                url: 'https://example.com/community?from="socials"',
                handle: null,
                icon: 'link',
                capabilities: [],
            ),
        ],
    );

    $html = renderSocialsPublicView($renderData);

    expect($html)
        ->toContain('capell-socials--center')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('&lt;script&gt;window.leak = true&lt;/script&gt;Follow us')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;Community')
        ->toContain('https://example.com/community?from=&quot;socials&quot;')
        ->not->toContain('<script>')
        ->not->toContain('<img')
        ->not->toContain('data-model-id')
        ->not->toContain('data-field-path')
        ->not->toContain('frontend-authoring')
        ->not->toContain('signed-editor')
        ->not->toContain('Capell\\Socials');
});

it('keeps database access out of the public Blade boundary', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/blocks/socials.blade.php');

    expect($view)->toBeString()
        ->not->toContain('::query(')
        ->not->toContain('DB::')
        ->not->toContain('loadMissing(')
        ->not->toContain('auth()')
        ->not->toContain('wire:');
});

function renderSocialsPublicView(SocialFollowRenderData $renderData): string
{
    $filesystem = new Filesystem;
    $cachePath = sys_get_temp_dir() . '/capell-socials-blade-' . bin2hex(random_bytes(8));
    $filesystem->makeDirectory($cachePath);

    try {
        $compiler = new BladeCompiler($filesystem, $cachePath);
        $resolver = new EngineResolver;
        $resolver->register('blade', static fn (): CompilerEngine => new CompilerEngine($compiler, $filesystem));
        $finder = new FileViewFinder($filesystem, [dirname(__DIR__, 2) . '/resources/views']);
        $finder->addNamespace('capell-socials', dirname(__DIR__, 2) . '/resources/views');
        $factory = new Factory($resolver, $finder, new Dispatcher(Container::getInstance()));

        return $factory->make('capell-socials::blocks.socials', compact('renderData'))->render();
    } finally {
        $filesystem->deleteDirectory($cachePath);
    }
}
