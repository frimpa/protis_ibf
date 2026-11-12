<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Calendar;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\ProjectService;
use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\View\BaseHtmlView;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
    public ?object $person = null;
    public array $people = [];
    public int $year;
    public int $month;
    public array $layouts = ProjectService::LAYOUTS;

    protected function prepare(): void
    {
        $this->active    = 'calendar';
        $this->pageTitle = 'Calendario';
        $input           = Factory::getApplication()->getInput();
        // di default il mese precedente: il mese in corso si rendiconta a mese concluso
        $prev            = strtotime('first day of last month');
        $this->year      = $input->getInt('year', (int) date('Y', $prev));
        $this->month     = $input->getInt('month', (int) date('n', $prev));
        $this->person    = $this->me;

        $id = $input->getInt('person', 0);

        if ($id && $this->me && $id !== (int) $this->me->id) {
            $p = Services::people()->get($id);

            if ($p && Services::people()->canAccess($this->me, $p)) {
                $this->person = $p;
            }
        }

        if ($this->canManage) {
            $this->people = Services::people()->listVisible($this->me, '', true);
        }

        $this->getDocument()->getWebAssetManager()->useScript('com_protis.calendar');
    }
}
