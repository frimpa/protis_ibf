<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Plugin\Console\Protis\Extension;

\defined('_JEXEC') or die;

use Cnr\Plugin\Console\Protis\Command\EpasSyncCommand;
use Cnr\Plugin\Console\Protis\Command\MigrateV2Command;
use Joomla\Application\ApplicationEvents;
use Joomla\Application\Event\ApplicationEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

final class ProtisConsole extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ApplicationEvents::BEFORE_EXECUTE => 'registerCommands'];
    }

    public function registerCommands(ApplicationEvent $event): void
    {
        $app = $event->getApplication();
        $app->addCommand(new EpasSyncCommand());
        $app->addCommand(new MigrateV2Command());
    }
}
