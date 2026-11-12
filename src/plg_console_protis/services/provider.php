<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

\defined('_JEXEC') or die;

use Cnr\Plugin\Console\Protis\Extension\ProtisConsole;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            static fn (Container $container) => new ProtisConsole((array) PluginHelper::getPlugin('console', 'protis'))
        );
    }
};
