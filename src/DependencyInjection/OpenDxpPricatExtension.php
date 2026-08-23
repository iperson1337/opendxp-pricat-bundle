<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\DependencyInjection;

use Iperson1337\OpenDxpPricatBundle\Model\PricatConfig;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class OpenDxpPricatExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        // PricatConfig is the single typed entry point to the settings; services autowire it.
        $container->getDefinition(PricatConfig::class)->setArgument('$config', $config);
        $container->setParameter(Configuration::ROOT . '.permission', $config['permission']);
    }

    public function getAlias(): string
    {
        return Configuration::ROOT;
    }
}
