<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;

/**
 * Abilita i plugin PROTIS IBF al primo install (l'LDAP resta da configurare con le credenziali del bind tecnico).
 */
return new class () implements InstallerScriptInterface {
    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // impostazione del sito (una sola volta, anche sugli aggiornamenti da versioni precedenti)
        $this->siteSetup($db);

        if ($type !== 'install' && $type !== 'discover_install') {
            return true;
        }

        $db->setQuery(
            "UPDATE #__extensions SET enabled = 1 WHERE type = 'plugin' AND ((folder = 'authentication' AND element = 'protisldap') OR (folder = 'console' AND element = 'protis'))"
        )->execute();

        Factory::getApplication()->enqueueMessage(
            'PROTIS IBF installato. Configura il plugin "Autenticazione - LDAP CNR (PROTIS IBF)" con il DN e la password del bind tecnico, poi imposta sedi e credenziali ePAS dal sito (PROTIS IBF → Impostazioni).',
            'message'
        );

        return true;
    }

    /**
     * PROTIS IBF come pagina iniziale, template "app" come predefinito, niente pulsante passkey.
     * Eseguito una volta sola (flag nei parametri del componente) per non sovrascrivere scelte successive.
     */
    private function siteSetup(DatabaseInterface $db): void
    {
        try {
            $params = (string) $db->setQuery("SELECT params FROM #__extensions WHERE element = 'com_protis' AND type = 'component'")->loadResult();
            $p      = json_decode($params ?: '{}', true) ?: [];

            if (!empty($p['site_setup_done'])) {
                return;
            }

            $menuId = (int) $db->setQuery("SELECT id FROM #__menu WHERE client_id = 0 AND published = 1 AND link = 'index.php?option=com_protis&view=home' ORDER BY id LIMIT 1")->loadResult();

            if ($menuId) {
                $db->setQuery("UPDATE #__menu SET home = 0 WHERE client_id = 0 AND home = 1 AND language = '*'")->execute();
                $db->setQuery('UPDATE #__menu SET home = 1, language = ' . $db->quote('*') . ' WHERE id = ' . $menuId)->execute();
            }

            $styleId = (int) $db->setQuery("SELECT id FROM #__template_styles WHERE client_id = 0 AND template = 'protis' ORDER BY id LIMIT 1")->loadResult();

            if ($styleId) {
                $db->setQuery("UPDATE #__template_styles SET home = '0' WHERE client_id = 0")->execute();
                $db->setQuery("UPDATE #__template_styles SET home = '1' WHERE id = " . $styleId)->execute();
            }

            // accesso solo con credenziali CNR: niente "Accedi con una passkey"
            $db->setQuery("UPDATE #__extensions SET enabled = 0 WHERE type = 'plugin' AND folder = 'system' AND element = 'webauthn'")->execute();

            $p['site_setup_done'] = 1;
            $db->setQuery('UPDATE #__extensions SET params = ' . $db->quote(json_encode($p)) . " WHERE element = 'com_protis' AND type = 'component'")->execute();

            Factory::getApplication()->enqueueMessage('PROTIS IBF impostato come pagina iniziale del sito con il template PROTIS IBF; accesso con passkey disattivato.', 'message');
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage('Impostazione del sito non completata: ' . $e->getMessage(), 'warning');
        }
    }
};
