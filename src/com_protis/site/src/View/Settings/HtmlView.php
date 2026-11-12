<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Settings;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\View\BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $sites = [];
    public array $epasPeople = [];
    public bool $pdflatex = false;
    public bool $spreadsheet = false;
    public bool $ldap = false;
    public ?array $epasTest = null;

    protected function prepare(): void
    {
        $this->active      = 'settings';
        $this->pageTitle   = 'Impostazioni';
        $this->sites       = Services::people()->sites();
        $this->epasPeople  = Services::epas()->syncablePeople();
        $this->pdflatex    = is_executable((string) $this->cparams->get('pdflatex_path', '/usr/bin/pdflatex'));
        $this->spreadsheet = \Cnr\Component\Protis\Administrator\Service\SpreadsheetRenderer::available();
        $this->ldap        = \extension_loaded('ldap');

        $app            = \Joomla\CMS\Factory::getApplication();
        $this->epasTest = $app->getUserState('com_protis.epastest') ?: null;
        $app->setUserState('com_protis.epastest', null);
    }
}
