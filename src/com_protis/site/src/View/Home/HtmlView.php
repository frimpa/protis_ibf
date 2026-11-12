<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Home;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;
use Cnr\Component\Protis\Administrator\Service\PeopleService;
use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\View\BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $missing = [];
    public array $projects = [];
    public array $month = [];
    public int $year = 0;
    public int $monthNum = 0;
    public string $kicker = '';
    public bool $showArchived = false;
    public int $archivedCount = 0;

    protected function prepare(): void
    {
        $this->active    = 'home';
        $this->pageTitle = 'Home';

        if (!$this->me) {
            return;
        }

        $in             = \Joomla\CMS\Factory::getApplication()->getInput();
        $this->missing  = PeopleService::missingFields($this->me);
        $this->showArchived = $in->getBool('archivati', false);
        $all            = Services::projects()->forPerson((int) $this->me->id, false);
        $this->archivedCount = \count(array_filter($all, static fn ($p) => !(int) $p->published));
        $this->projects = array_values(array_filter($all, fn ($p) => $this->showArchived || (int) $p->published));

        // mese mostrato: ?ym=YYYY-MM, altrimenti il mese da rendicontare (il precedente)
        $ym = $in->getString('ym', '');
        $ts = preg_match('/^(\d{4})-(\d{2})$/', $ym, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12 && (int) $m[1] >= 2000 && (int) $m[1] <= 2100
            ? mktime(0, 0, 0, (int) $m[2], 1, (int) $m[1])
            : strtotime('first day of last month');
        $this->year     = (int) date('Y', $ts);
        $this->monthNum = (int) date('n', $ts);
        $this->month    = Services::calendar()->month($this->me, $this->year, $this->monthNum, Services::projects())['totals'];
        $this->month['label'] = Time::monthName($this->monthNum) . ' ' . $this->year;

        $cur  = (int) date('Y') * 12 + (int) date('n');
        $sel  = $this->year * 12 + $this->monthNum;
        $this->kicker = $sel === $cur - 1 ? 'Mese da rendicontare' : ($sel === $cur ? 'Mese in corso' : ($sel > $cur ? 'Mese futuro' : 'Mese passato'));
    }
}
