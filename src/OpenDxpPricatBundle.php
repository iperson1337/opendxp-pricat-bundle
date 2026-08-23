<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle;

use Iperson1337\OpenDxpPricatBundle\DependencyInjection\OpenDxpPricatExtension;
use OpenDxp\Extension\Bundle\AbstractOpenDxpBundle;
use OpenDxp\Extension\Bundle\Installer\InstallerInterface;
use OpenDxp\Extension\Bundle\OpenDxpBundleAdminClassicInterface;
use OpenDxp\Extension\Bundle\Traits\BundleAdminClassicTrait;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * PRICAT bundle: imports EDI PRICAT XML price catalogues into the `Pricat` / `PricatPosition`
 * DataObjects and lets a content manager reconcile every position against the matching product
 * (compare → accept / decline) from a dedicated admin tab.
 *
 * Assets are published by `assets:install` under /bundles/opendxppricat/ (short bundle name, lowercased).
 * `opendxp:bundle:install OpenDxpPricatBundle` creates the admin permission (see Installer).
 */
class OpenDxpPricatBundle extends AbstractOpenDxpBundle implements OpenDxpBundleAdminClassicInterface
{
    use BundleAdminClassicTrait;

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new OpenDxpPricatExtension();
        }

        return $this->extension;
    }

    /**
     * Load order matters: models and windows first, panels next, the startup (menu) last.
     *
     * @return string[]
     */
    public function getJsPaths(): array
    {
        return [
            '/bundles/opendxppricat/js/opendxp/models.js',
            '/bundles/opendxppricat/js/opendxp/importWindow.js',
            '/bundles/opendxppricat/js/opendxp/compareWindow.js',
            '/bundles/opendxppricat/js/opendxp/positionsPanel.js',
            '/bundles/opendxppricat/js/opendxp/pricatPanel.js',
            '/bundles/opendxppricat/js/opendxp/startup.js',
        ];
    }

    /**
     * @return string[]
     */
    public function getCssPaths(): array
    {
        return [
            '/bundles/opendxppricat/css/pricat.css',
        ];
    }

    public function getInstaller(): ?InstallerInterface
    {
        return $this->container->get(Installer::class);
    }
}
