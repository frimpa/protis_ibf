<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\View\Dashboard;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\SpreadsheetRenderer;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

class HtmlView extends BaseHtmlView
{
    public array $counts = [];
    public array $checks = [];

    public function display($tpl = null)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach (['people' => 'Persone', 'projects' => 'Progetti', 'workpackages' => 'Work package', 'assignments' => 'Associazioni', 'days' => 'Giornate', 'hours' => 'Registrazioni ore', 'absence_codes' => 'Codici assenza', 'sites' => 'Sedi'] as $t => $label) {
            $this->counts[$label] = (int) $db->setQuery('SELECT COUNT(*) FROM #__protis_' . $t)->loadResult();
        }

        $pdflatex     = (string) ComponentHelper::getParams('com_protis')->get('pdflatex_path', '/usr/bin/pdflatex');
        $this->checks = [
            'pdflatex (' . $pdflatex . ')'       => is_executable($pdflatex),
            'PhpSpreadsheet (Excel/ODS)'         => SpreadsheetRenderer::available(),
            'Estensione PHP ldap'                => \extension_loaded('ldap'),
            'Estensione PHP sodium'              => \extension_loaded('sodium'),
            'Estensione PHP curl'                => \extension_loaded('curl'),
            'Plugin autenticazione LDAP PROTIS IBF'  => (bool) \Joomla\CMS\Plugin\PluginHelper::isEnabled('authentication', 'protisldap'),
            'Plugin console PROTIS IBF (cron/migrazione)' => (bool) \Joomla\CMS\Plugin\PluginHelper::isEnabled('console', 'protis'),
        ];

        ToolbarHelper::title('PROTIS IBF', 'clock');

        if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_protis')) {
            ToolbarHelper::preferences('com_protis');
        }

        parent::display($tpl);
    }
}
