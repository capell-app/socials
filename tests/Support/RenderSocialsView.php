<?php

declare(strict_types=1);

use Capell\Socials\Data\SocialFollowRenderData;
use Capell\Socials\Data\SocialShareRenderData;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

/** @param view-string $view */
function renderSocialsView(string $view, SocialFollowRenderData|SocialShareRenderData|null $renderData): string
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

        return $factory->make($view, compact('renderData'))->render();
    } finally {
        $filesystem->deleteDirectory($cachePath);
    }
}
