<?php
namespace League\Tactician\Bundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * This compiler pass maps Handler DI tags to specific commands
 */
class CommandHandlerPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @throws \Exception
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('tactician.handler.locator.symfony')) {
            throw new \Exception('Missing tactician.handler.locator.symfony definition');
        }

        $handlerLocator = $container->findDefinition('tactician.handler.locator.symfony');

        $defaultMapping = [];
        $defaultBusId = $this->getDefaultBusId($container);
        $busIdToHandlerMapping = [];

        foreach ($container->findTaggedServiceIds('tactician.handler') as $id => $tags) {

            foreach ($tags as $attributes) {
                if (!isset($attributes['command'])) {
                    throw new \Exception('The tactician.handler tag must always have a command attribute');
                }

                if (array_key_exists('bus', $attributes)) {
                    $this->abortIfInvalidBusId($attributes['bus'], $container);
                }

                $busId = array_key_exists('bus', $attributes) ? $attributes['bus'] : $defaultBusId;

                $busIdToHandlerMapping[$busId][$attributes['command']] = $id;
            }
        }

        foreach ($busIdToHandlerMapping as $busId => $handlerMapping) {
            $locatorServiceId = 'tactician.commandbus.'.$busId.'.handler.locator';
            $container->setDefinition(
                $locatorServiceId,
                $this->buildLocatorDefinition($handlerMapping)
            );
        }

        $handlerLocator->addArgument($defaultMapping);
    }

    protected function getDefaultBusId(ContainerBuilder $container)
    {
        $config = $container->getExtensionConfig('tactician');

        return $config['default_bus'];
    }

    /**
     * @param string $id
     * @param ContainerBuilder $container 
     * @throws Exception
     */
    protected function abortIfInvalidBusId($id, ContainerBuilder $container)
    {
        $config = $container->getExtensionConfig('tactician');

        if (!array_key_exists($id, $config['commandbus'])) {
            throw new \Exception('Invalid bus id "'.$id.'". Valid buses are: '.implode(', ', array_keys($config['commandbus'])));
        }
    }

    /**
     * @param array $handlerMapping 
     * @return Definition
     */
    protected function buildLocatorDefinition(array $handlerMapping)
    {
        return new Definition(
            'League\Tactician\Bundle\Handler\ContainerBasedHandlerLocator',
            [
                new Reference('service_container'),
                $handlerMapping,
            ]
        );
    }

}
