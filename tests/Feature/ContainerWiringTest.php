<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Command\StdinConfirmationPrompter;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Path\ProjectPaths;
use Marko\DevAi\Commands\InstallCommand;
use Marko\DevAi\Commands\UpdateCommand;
use Marko\DevAi\Process\CommandRunner;
use Marko\DevAi\Process\CommandRunnerInterface;

function bootDevAiContainer(): Container
{
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ProjectPaths::class, new ProjectPaths(sys_get_temp_dir()));
    // What Application binds and CommandRunner registers for a running command
    $container->bind(ConfirmationPrompterInterface::class, StdinConfirmationPrompter::class);
    $container->instance(Input::class, new Input(['marko', 'devai:install']));
    $container->instance(Output::class, new Output(fopen('php://memory', 'w')));
    // What marko/config binds in a running app, loaded with devai's shipped defaults
    $container->instance(
        ConfigRepositoryInterface::class,
        new ConfigRepository(['devai' => require dirname(__DIR__, 2) . '/config/devai.php']),
    );

    $parser = new ManifestParser();
    $registry = new BindingRegistry($container);

    $registry->registerModule($parser->parse(dirname(__DIR__, 3) . '/codeindexer'));
    $registry->registerModule($parser->parse(dirname(__DIR__, 2)));

    return $container;
}

it('resolves InstallCommand through the container', function (): void {
    $container = bootDevAiContainer();

    expect($container->get(InstallCommand::class))
        ->toBeInstanceOf(InstallCommand::class);
});

it('resolves UpdateCommand through the container', function (): void {
    $container = bootDevAiContainer();

    expect($container->get(UpdateCommand::class))
        ->toBeInstanceOf(UpdateCommand::class);
});

it('binds CommandRunnerInterface to CommandRunner', function (): void {
    $container = bootDevAiContainer();

    expect($container->get(CommandRunnerInterface::class))
        ->toBeInstanceOf(CommandRunner::class);
});
