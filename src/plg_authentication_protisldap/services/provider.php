<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

\defined('_JEXEC') or die;

use Cnr\Plugin\Authentication\ProtisLdap\Extension\ProtisLdap;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            static function (Container $container) {
                $plugin = new ProtisLdap((array) PluginHelper::getPlugin('authentication', 'protisldap'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
