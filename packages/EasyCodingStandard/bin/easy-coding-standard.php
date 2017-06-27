<?php declare(strict_types=1);

use Symfony\Component\Console\Application;
use Symplify\EasyCodingStandard\DependencyInjection\ContainerFactory;

// performance boost
gc_disable();

// prefer local vendor over analyzed project (e.g. for "composer create-project symplify/easy-coding-standard")
$possibleAutoloadPaths = [
    __DIR__ . '/../../..',
    __DIR__ . '/../vendor',
    getcwd() . '/vendor',
];

foreach ($possibleAutoloadPaths as $possibleAutoloadPath) {
    if (file_exists($possibleAutoloadPath . '/autoload.php')) {
        require_once $possibleAutoloadPath . '/autoload.php';
        require_once $possibleAutoloadPath . '/squizlabs/php_codesniffer/autoload.php';
        break;
    }
}

// 1. build DI container
$container = (new ContainerFactory)->create();

// 2. Run Console Application
/** @var Application $application */
$application = $container->get(Application::class);
$application->run();