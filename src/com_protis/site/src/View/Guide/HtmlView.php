<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Guide;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Acl;
use Cnr\Component\Protis\Administrator\Helper\Markdown;
use Cnr\Component\Protis\Site\View\BaseHtmlView;

/**
 * Guida d'uso: il testo è nei file site/guide/*.md (si aggiorna con il pacchetto).
 * Utenti e visitatori vedono la parte per gli utenti, i responsabili anche la loro, gli amministratori tutto.
 */
class HtmlView extends BaseHtmlView
{
    public string $html = '';
    /** @var array<int,array{id:string,title:string}> */
    public array $toc = [];
    public bool $guest = true;

    protected function prepare(): void
    {
        $this->active    = 'guide';
        $this->pageTitle = 'Guida';
        $this->guest     = Acl::user()->guest;

        $files = ['1-utenti.md'];

        if (!$this->guest && $this->canManage) {
            $files[] = '2-responsabili.md';
        }

        if (!$this->guest && $this->isAdmin) {
            $files[] = '3-amministratori.md';
        }

        $md  = new Markdown();
        $out = [];

        foreach ($files as $f) {
            $path = JPATH_SITE . '/components/com_protis/guide/' . $f;

            if (is_file($path)) {
                $out[] = $md->render((string) file_get_contents($path));
            }
        }

        $this->html = implode("\n", $out);
        $this->toc  = $md->toc;
    }
}
