<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Register the console commands. Value objects (Dev\) and the Composer script
    // handler (Composer\) are not services and are excluded.
    $services->load('Dktaylor\\DevToolkit\\', \dirname(__DIR__).'/src/')
        ->exclude([
            \dirname(__DIR__).'/src/DevToolkitBundle.php',
            \dirname(__DIR__).'/src/Dev/',
            \dirname(__DIR__).'/src/Composer/',
        ]);
};