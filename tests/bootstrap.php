<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;

$container = new Container;
$loader = new ArrayLoader;
$loader->addMessages('en', 'capell-socials::socials', require dirname(__DIR__) . '/resources/lang/en/socials.php');
$container->instance('translator', new Translator($loader, 'en'));
Container::setInstance($container);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Capell\\Socials\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
