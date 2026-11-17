<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Rules;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;

return new class () implements InstallerScriptInterface {
    private string $minimumPhp    = '8.3.0';
    private string $minimumJoomla = '6.0.0';

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
        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            Factory::getApplication()->enqueueMessage('PROTIS IBF richiede PHP ' . $this->minimumPhp . ' o superiore.', 'error');

            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Factory::getApplication()->enqueueMessage('PROTIS IBF richiede Joomla ' . $this->minimumJoomla . ' o superiore.', 'error');

            return false;
        }

        foreach (['sodium', 'curl', 'mysqli', 'zip', 'gd'] as $ext) {
            if (!\extension_loaded($ext)) {
                Factory::getApplication()->enqueueMessage("Estensione PHP mancante: $ext", 'warning');
            }
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if (!\in_array($type, ['install', 'discover_install', 'update'], true)) {
            return true;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // favicon anche nella radice del sito (richiesta in automatico dai browser), solo se non ce n'è già una
        $ico = JPATH_ROOT . '/media/com_protis/images/icons/favicon.ico';

        if (is_file($ico) && !is_file(JPATH_ROOT . '/favicon.ico')) {
            @copy($ico, JPATH_ROOT . '/favicon.ico');
        }

        $dir = JPATH_ROOT . '/images/protis/loghi';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // 3.21: il gruppo "PROTIS Responsabili IBF" diventa "PROTIS Responsabili" (stessi membri e permessi)
        $db->setQuery("UPDATE #__usergroups SET title = 'PROTIS Responsabili' WHERE title = 'PROTIS Responsabili IBF'
            AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM #__usergroups WHERE title = 'PROTIS Responsabili') x)")->execute();

        if ($type === 'update') {
            $this->keepLegacyBranding($db);
        }

        // Gruppi utente PROTIS IBF (figli di "Registered")
        $registered = (int) $db->setQuery("SELECT id FROM #__usergroups WHERE title = 'Registered'")->loadResult() ?: 2;
        $groups     = [];

        foreach (['PROTIS Responsabili', 'PROTIS Amministratori'] as $title) {
            $id = (int) $db->setQuery('SELECT id FROM #__usergroups WHERE title = ' . $db->quote($title))->loadResult();

            if (!$id) {
                $table = Table::getInstance('Usergroup', 'Joomla\\CMS\\Table\\', ['dbo' => $db]);
                $table->save(['parent_id' => $registered, 'title' => $title]);
                $id = (int) $table->id;
            }

            $groups[$title] = $id;
        }

        // 3.19.1: il gruppo "PROTIS Responsabili di sede" non esiste più; chi ne faceva parte passa a "PROTIS Responsabili"
        $this->dropSiteManagersGroup($db, $groups['PROTIS Responsabili']);

        // Permessi del componente (si aggiungono a quelli già presenti, senza sovrascriverli)
        $asset = Table::getInstance('Asset', 'Joomla\\CMS\\Table\\', ['dbo' => $db]);

        if ($asset->loadByName('com_protis')) {
            $rules = new Rules($asset->rules);
            $rules->mergeAction('protis.manage.all', [$groups['PROTIS Responsabili'] => 1, $groups['PROTIS Amministratori'] => 1]);
            $rules->mergeAction('core.admin', [$groups['PROTIS Amministratori'] => 1]);
            $rules->mergeAction('core.manage', [$groups['PROTIS Amministratori'] => 1]);
            $rules->mergeAction('core.options', [$groups['PROTIS Amministratori'] => 1]);
            $asset->rules = (string) $rules;
            $asset->store();
        }

        if ($type !== 'update') {
            $this->createMenuItem($db);
        }

        return true;
    }

    /**
     * Fino alla 3.20 istituto e scritte avevano come valori predefiniti quelli dell'IBF. Dalla 3.21 i
     * predefiniti sono neutri: chi aggiorna e non li aveva mai salvati nelle Opzioni li ritrova invariati.
     */
    private function keepLegacyBranding(DatabaseInterface $db): void
    {
        $raw    = (string) $db->setQuery("SELECT params FROM #__extensions WHERE element = 'com_protis' AND type = 'component'")->loadResult();
        $params = json_decode($raw ?: '{}', true) ?: [];

        if (\array_key_exists('istituto', $params) && \array_key_exists('brand_istituto', $params)) {
            return; // opzioni già salvate: si rispettano
        }

        $legacy = ['istituto' => 'CNR - Istituto di Biofisica', 'brand_istituto' => 'Istituto di Biofisica'];

        foreach ($legacy as $k => $v) {
            if (!\array_key_exists($k, $params)) {
                $params[$k] = $v;
            }
        }

        $db->setQuery('UPDATE #__extensions SET params = ' . $db->quote(json_encode($params)) . " WHERE element = 'com_protis' AND type = 'component'")->execute();
    }

    private function dropSiteManagersGroup(DatabaseInterface $db, int $ibfGroup): void
    {
        $old = (int) $db->setQuery("SELECT id FROM #__usergroups WHERE title = 'PROTIS Responsabili di sede'")->loadResult();

        if (!$old) {
            return;
        }

        $users = $db->setQuery('SELECT user_id FROM #__user_usergroup_map WHERE group_id = ' . $old)->loadColumn();

        foreach ($users as $uid) {
            $db->setQuery('INSERT IGNORE INTO #__user_usergroup_map (user_id, group_id) VALUES (' . (int) $uid . ', ' . $ibfGroup . ')')->execute();
        }

        $table = Table::getInstance('Usergroup', 'Joomla\\CMS\\Table\\', ['dbo' => $db]);
        $table->delete($old);

        if ($users) {
            Factory::getApplication()->enqueueMessage(\count($users) . ' utenti del gruppo "PROTIS Responsabili di sede" (eliminato) sono stati spostati in "PROTIS Responsabili".', 'warning');
        }
    }

    /** Voce "PROTIS IBF" nel menu principale del sito (solo al primo install). */
    private function createMenuItem(DatabaseInterface $db): void
    {
        $componentId = (int) $db->setQuery("SELECT extension_id FROM #__extensions WHERE element = 'com_protis' AND type = 'component'")->loadResult();
        $exists      = (int) $db->setQuery("SELECT COUNT(*) FROM #__menu WHERE link LIKE 'index.php?option=com_protis%' AND client_id = 0")->loadResult();

        if (!$componentId || $exists) {
            return;
        }

        $menutype = $db->setQuery("SELECT menutype FROM #__menu_types WHERE client_id = 0 ORDER BY id LIMIT 1")->loadResult() ?: 'mainmenu';

        $menu = Table::getInstance('Menu', 'Joomla\\CMS\\Table\\', ['dbo' => $db]);
        $menu->setLocation(1, 'last-child');
        $data = [
            'menutype'     => $menutype,
            'title'        => 'PROTIS IBF',
            'alias'        => 'protis',
            'path'         => 'protis',
            'link'         => 'index.php?option=com_protis&view=home',
            'type'         => 'component',
            'published'    => 1,
            'parent_id'    => 1,
            'component_id' => $componentId,
            'access'       => 1,
            'params'       => '{}',
            'language'     => '*',
            'client_id'    => 0,
        ];

        if ($menu->bind($data) && $menu->check() && $menu->store()) {
            $menu->rebuildPath($menu->id);
        }
    }
};
