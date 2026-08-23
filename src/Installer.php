<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle;

use Iperson1337\OpenDxpPricatBundle\Model\PricatConfig;
use OpenDxp\Db;
use OpenDxp\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use OpenDxp\Model\DataObject\ClassDefinition;
use OpenDxp\Model\User\Permission;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * `bin/console opendxp:bundle:install OpenDxpPricatBundle`
 *
 * 1. Creates the `Pricat` and `PricatPosition` DataObject classes from `install/class_*_export.json`
 *    **only when a class of that name does not exist yet**. Existing definitions (an application that
 *    already owns the classes, with data behind them) are never modified — the application's
 *    `var/classes/definition_*.php` stays the source of truth.
 * 2. Creates the admin permission the menu entry and every endpoint are guarded with (key from
 *    `iperson1337_opendxp_pricat.permission`). Idempotent: an existing definition is kept as is.
 *
 * Uninstall removes the permission definition only; classes and their objects are left untouched.
 */
final class Installer extends SettingsStoreAwareInstaller
{
    public const string PERMISSION_CATEGORY = 'PRICAT';

    /** Import order matters: PricatPosition has a many-to-one relation to Pricat. */
    public const array CLASS_NAMES = ['Pricat', 'PricatPosition'];

    public function __construct(
        BundleInterface $bundle,
        private readonly PricatConfig $config,
    ) {
        parent::__construct($bundle);
    }

    public function install(): void
    {
        foreach (self::CLASS_NAMES as $className) {
            $this->installClassIfMissing($className);
        }

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

    private function installClassIfMissing(string $className): void
    {
        if (ClassDefinition::getByName($className) !== null) {
            $this->output->writeln(sprintf('DataObject class <info>%s</info> already exists — left untouched.', $className));

            return;
        }

        $file = sprintf('%s/install/class_%s_export.json', $this->bundle->getPath(), $className);
        $json = is_readable($file) ? file_get_contents($file) : false;
        if ($json === false) {
            throw new \RuntimeException(sprintf('Class export %s is missing or unreadable', $file));
        }

        $class = new ClassDefinition();
        $class->setName($className);
        ClassDefinition\Service::importClassDefinitionFromJson($class, $json, true);

        $this->output->writeln(sprintf('DataObject class <info>%s</info> created from %s.', $className, basename($file)));
    }
}
