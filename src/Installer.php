<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle;

use Iperson1337\OpenDxpPricatBundle\Model\PricatConfig;
use OpenDxp\Db;
use OpenDxp\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use OpenDxp\Model\User\Permission;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * `bin/console opendxp:bundle:install OpenDxpPricatBundle`
 *
 * Creates the admin permission the menu entry and every endpoint are guarded with (key from
 * `iperson1337_opendxp_pricat.permission`). Idempotent: an existing definition is kept as is.
 * Uninstall removes the definition; user/role grants referencing it are dropped by OpenDXP.
 */
final class Installer extends SettingsStoreAwareInstaller
{
    public const string PERMISSION_CATEGORY = 'PRICAT';

    public function __construct(
        BundleInterface $bundle,
        private readonly PricatConfig $config,
    ) {
        parent::__construct($bundle);
    }

    public function install(): void
    {
        Permission\Definition::create($this->config->permission)
            ->setCategory(self::PERMISSION_CATEGORY)
            ->save();

        parent::install();
    }

    public function uninstall(): void
    {
        // `key` is a reserved word — DBAL's delete() does not quote identifiers
        Db::get()->executeStatement('DELETE FROM users_permission_definitions WHERE `key` = ?', [$this->config->permission]);

        parent::uninstall();
    }

    public function needsReloadAfterInstall(): bool
    {
        return true;
    }
}
