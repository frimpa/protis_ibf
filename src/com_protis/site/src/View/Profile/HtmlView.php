<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Profile;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\PeopleService;
use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\View\BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $sites = [];
    public array $contracts = [];
    public array $missing = [];
    public array $assignments = [];

    protected function prepare(): void
    {
        $this->active      = 'profile';
        $this->pageTitle   = 'Profilo';
        $this->sites       = Services::people()->instituteSites();
        $this->contracts   = Services::people()->contractTypes();
        $this->missing     = $this->me ? PeopleService::missingFields($this->me) : [];
        $this->assignments = $this->me ? Services::projects()->assignments(null, (int) $this->me->id) : [];
    }
}
