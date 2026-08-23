<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\EventSubscriber;

use Iperson1337\OpenDxpPricatBundle\Model\PricatConfig;
use OpenDxp\Bundle\AdminBundle\Event\AdminEvents;
use OpenDxp\Bundle\AdminBundle\Event\IndexActionSettingsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Exposes the bundle settings the admin JS needs as `opendxp.settings.pricat`
 * (permission key for the menu guard, page size for the grids).
 */
final class AdminSettingsSubscriber implements EventSubscriberInterface
{
    public const string SETTINGS_KEY = 'pricat';

    public function __construct(private readonly PricatConfig $config)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [AdminEvents::INDEX_ACTION_SETTINGS => 'onIndexSettings'];
    }

    public function onIndexSettings(IndexActionSettingsEvent $event): void
    {
        $event->addSetting(self::SETTINGS_KEY, [
            'permission' => $this->config->permission,
            'pageSize' => $this->config->pageSize,
        ]);
    }
}
