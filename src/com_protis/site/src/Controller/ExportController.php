<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\Controller;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Acl;
use Cnr\Component\Protis\Administrator\Service\IntervalTimesheet;
use Cnr\Component\Protis\Administrator\Service\LatexRenderer;
use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Administrator\Service\SpreadsheetRenderer;
use Cnr\Component\Protis\Administrator\Service\TimesheetService;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Esportazione timesheet: PDF (LaTeX), XLSX, ODS, CSV, TEX.
 *  - export.timesheet → una persona, un progetto, uno o più mesi (una pagina per mese)
 *  - export.project   → tutte le persone associate a un progetto (responsabili)
 */
class ExportController extends BaseController
{
    use ProtisControllerTrait;

    private const MIME = [
        'pdf'  => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'csv'  => 'text/csv; charset=utf-8',
        'tex'  => 'application/x-tex; charset=utf-8',
    ];

    private function range(): array
    {
        $from = $this->input->getString('from', date('Y-m'));
        $to   = $this->input->getString('to', $from);

        if (!preg_match('/^\d{4}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}$/', $to) || $to < $from) {
            throw new \InvalidArgumentException('Intervallo di mesi non valido.');
        }

        return TimesheetService::monthsBetween($from, $to);
    }

    public function timesheet(): void
    {
        try {
            $this->requireToken();
            $person    = $this->targetPerson();
            $projectId = $this->input->getInt('project');
            $months    = $this->range();

            $allowed = array_map(static fn ($p) => (int) $p->id, Services::projects()->forPerson((int) $person->id, false));

            if (!\in_array($projectId, $allowed, true) && !Acl::canManage()) {
                throw new \DomainException('Progetto non associato.');
            }

            $sheets = [];

            foreach ($months as [$y, $m]) {
                $sheets[] = Services::timesheet()->build((int) $person->id, $projectId, $y, $m);
            }

            $name = $sheets[0]['filename'] . (\count($sheets) > 1 ? '_' . \count($sheets) . 'mesi' : '');
            $this->send($sheets, $name);
        } catch (\Throwable $e) {
            $this->back($e);
        }
    }

    public function project(): void
    {
        try {
            $this->requireToken();
            $this->requireManager();

            $me        = $this->me();
            $projectId = $this->input->getInt('project');
            $months    = $this->range();
            $visible   = array_map(static fn ($p) => (int) $p->id, Services::people()->listVisible($me));
            $sheets    = [];

            foreach (Services::projects()->assignments($projectId, null, $visible) as $a) {
                foreach ($months as [$y, $m]) {
                    $s = Services::timesheet()->build((int) $a->person_id, $projectId, $y, $m);

                    // nel massivo si saltano le pagine senza ore sul progetto
                    if ($s['project_total'] > 0 || $this->input->getBool('empty')) {
                        $sheets[] = $s;
                    }
                }
            }

            if (!$sheets) {
                throw new \DomainException('Nessuna ora rendicontata sul progetto nel periodo selezionato.');
            }

            $p = Services::projects()->get($projectId);
            $this->send($sheets, preg_replace('/[^A-Za-z0-9]+/', '_', $p->acronimo) . '_' . $this->input->getString('from') . '_' . $this->input->getString('to'));
        } catch (\Throwable $e) {
            $this->back($e);
        }
    }

    /**
     * Timesheet per intervallo di una persona su un progetto (dashboard rendicontazione).
     * periodo=progetto → intera durata del progetto; periodo=scelto → from/to (YYYY-MM).
     */
    public function interval(): void
    {
        try {
            $this->requireToken();
            $this->requireManager();

            $me     = $this->me();
            $person = Services::people()->get($this->input->getInt('person'));

            if (!$person || !Services::people()->canAccess($me, $person)) {
                throw new \DomainException('Persona non accessibile.');
            }

            $whole  = $this->input->getCmd('periodo', 'progetto') !== 'scelto';
            $svc    = new IntervalTimesheet();
            $report = $svc->build(
                (int) $person->id,
                $this->input->getInt('project'),
                $whole ? null : $this->input->getString('from'),
                $whole ? null : $this->input->getString('to')
            );

            $format = $this->input->getCmd('format_out', 'pdf') === 'xlsx' ? 'xlsx' : 'pdf';
            $body   = $format === 'xlsx' ? $svc->xlsx($report) : $svc->pdf($report);
            $this->output($body, $format, $report['filename'], $format === 'pdf');
        } catch (\Throwable $e) {
            $this->back($e);
        }
    }

    /** Anteprima PDF dell'intestazione e della fascia loghi di un progetto (dati di esempio). */
    public function preview(): void
    {
        try {
            $this->requireToken();
            $this->requireManager();
            $this->input->set('format_out', 'pdf');
            $sheet = Services::timesheet()->buildSample($this->input->getInt('project'), (int) date('Y'), (int) date('n'));
            $this->send([$sheet], $sheet['filename']);
        } catch (\Throwable $e) {
            $this->back($e);
        }
    }

    private function send(array $sheets, string $basename): void
    {
        $format = $this->input->getCmd('format_out', 'pdf');

        $body = match ($format) {
            'pdf'         => (new LatexRenderer())->pdf($sheets),
            'tex'         => (new LatexRenderer())->tex($sheets),
            'xlsx', 'ods' => (new SpreadsheetRenderer())->spreadsheet($sheets, $format),
            'csv'         => (new SpreadsheetRenderer())->csv($sheets),
            default       => throw new \InvalidArgumentException('Formato non supportato.'),
        };

        $this->output($body, $format, $basename, $format === 'pdf' && $this->input->getBool('inline', true));
    }

    private function output(string $body, string $format, string $basename, bool $inline): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . self::MIME[$format]);
        header('Content-Length: ' . \strlen($body));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $basename . '.' . $format . '"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $body;
        $this->app->close();
    }

    private function back(\Throwable $e): void
    {
        $this->app->enqueueMessage($e->getMessage(), 'error');
        $return = $this->input->getBase64('return');
        $url    = $return ? base64_decode($return) : Route::_('index.php?option=com_protis&view=calendar', false);

        if (!\Joomla\CMS\Uri\Uri::isInternal($url)) {
            $url = Route::_('index.php?option=com_protis&view=calendar', false);
        }

        $this->setRedirect($url);
        $this->redirect();
    }
}
