<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\Controller;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Acl;
use Cnr\Component\Protis\Administrator\Helper\Time;
use Cnr\Component\Protis\Administrator\Service\PeopleService;
use Cnr\Component\Protis\Administrator\Service\Services;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Endpoint JSON usati dal calendario (index.php?option=com_protis&task=api.<azione>&format=json).
 */
class ApiController extends BaseController
{
    use ProtisControllerTrait;

    private function ym(): array
    {
        $y = $this->input->getInt('year', (int) date('Y'));
        $m = $this->input->getInt('month', (int) date('n'));

        if ($y < 2000 || $y > 2100 || $m < 1 || $m > 12) {
            throw new \InvalidArgumentException('Mese non valido.');
        }

        return [$y, $m];
    }

    private function monthPayload(object $person, int $y, int $m): array
    {
        $data = Services::calendar()->month($person, $y, $m, Services::projects());

        $data['canEditManual'] = !PeopleService::usesEpas($person);
        $data['canManage']     = Acl::canManage();

        return $data;
    }

    /** GET: dati del mese */
    public function month(): void
    {
        try {
            $person = $this->targetPerson();
            [$y, $m] = $this->ym();

            $synced = null;
            $params = ComponentHelper::getParams('com_protis');

            // sincronizzazione automatica (opzionale) del mese corrente, max 1 volta ogni 30 minuti
            if ($params->get('epas_autosync_on_open') && PeopleService::usesEpas($person) && $y === (int) date('Y') && $m === (int) date('n')) {
                $key  = 'protis.autosync.' . $person->id;
                $last = (int) $this->app->getSession()->get($key, 0);

                if (time() - $last > 1800) {
                    $this->app->getSession()->set($key, time());

                    try {
                        $synced = Services::epas()->syncMonth($person, $y, $m, Services::calendar());
                    } catch (\Throwable $e) {
                        $synced = ['error' => $e->getMessage()];
                    }
                }
            }

            $this->json(['success' => true, 'data' => $this->monthPayload($person, $y, $m), 'autosync' => $synced]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /** POST: ore WP di un giorno. hours[wpId]=HH:MM */
    public function saveDay(): void
    {
        try {
            $this->requireToken();
            $person = $this->targetPerson();
            $date   = $this->input->post->getString('date');
            $this->assertMonthEditable((int) substr($date, 0, 4), (int) substr($date, 5, 2));

            $raw   = (array) $this->input->post->get('hours', [], 'array');
            $hours = [];

            foreach ($raw as $wpId => $v) {
                $hours[(int) $wpId] = Time::toMinutes((string) $v);
            }

            // inserimento manuale presenze nello stesso salvataggio (solo personale senza matricola)
            $manual = $this->input->post->get('manual', null, 'array');
            $db     = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $db->transactionStart();

            try {
                if ($manual !== null) {
                    if (PeopleService::usesEpas($person)) {
                        throw new \DomainException('Le presenze del personale con matricola provengono da ePAS.');
                    }

                    $values = ['worked' => Time::toMinutes($manual['worked'] ?? '')];

                    foreach (array_keys(\Cnr\Component\Protis\Administrator\Service\CalendarService::CATEGORIES) as $cat) {
                        $values[$cat] = Time::toMinutes($manual[$cat] ?? '');
                    }

                    Services::calendar()->saveManualDay((int) $person->id, $date, $values);
                }

                $res = Services::calendar()->saveDay((int) $person->id, $date, $hours, Services::projects(), (int) Acl::user()->id);
                $db->transactionCommit();
            } catch (\Throwable $e) {
                $db->transactionRollback();

                throw $e;
            }

            [$y, $m] = [(int) substr($date, 0, 4), (int) substr($date, 5, 2)];

            $this->json([
                'success' => true,
                'message' => 'Salvato: ' . Time::format($res['reported']) . ' su ' . Time::format($res['capacity']) . ' rendicontabili.',
                'data'    => $this->monthPayload($person, $y, $m),
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /** POST: aggiorna timbrature da ePAS */
    public function sync(): void
    {
        try {
            $this->requireToken();
            $person  = $this->targetPerson();
            [$y, $m] = $this->ym();
            $res     = Services::epas()->syncMonth($person, $y, $m, Services::calendar());

            $this->json([
                'success' => true,
                'message' => \sprintf('Calendario di %s %d aggiornato da ePAS: %d giorni, %s ore di presenza.', Time::monthName($m), $y, $res['days'], Time::format($res['worked'])),
                'data'    => $this->monthPayload($person, $y, $m),
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /** POST: riempi il mese con un WP */
    public function fill(): void
    {
        try {
            $this->requireToken();
            $person  = $this->targetPerson();
            [$y, $m] = $this->ym();
            $this->assertMonthEditable($y, $m);

            // si usano solo le ore libere: le ore già inserite su altri progetti non vengono toccate
            $mode = 'residual';
            $res  = Services::calendar()->fillMonth((int) $person->id, $y, $m, $this->input->post->getInt('wp'), $mode, Services::projects(), (int) Acl::user()->id);

            $this->json([
                'success' => true,
                'message' => \sprintf('Assegnate %s ore su %d giorni.', Time::format($res['assigned']), $res['days']),
                'data'    => $this->monthPayload($person, $y, $m),
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /** POST: distribuisci monte ore mensile. targets[wpId]=HH:MM */
    public function distribute(): void
    {
        try {
            $this->requireToken();
            $person  = $this->targetPerson();
            [$y, $m] = $this->ym();
            $this->assertMonthEditable($y, $m);

            $targets = [];

            foreach ((array) $this->input->post->get('targets', [], 'array') as $wpId => $v) {
                if (trim((string) $v) !== '') {
                    $targets[(int) $wpId] = Time::toMinutes((string) $v);
                }
            }

            if (!$targets) {
                throw new \InvalidArgumentException('Indicare le ore per almeno un work package.');
            }

            $mode = $this->input->post->getCmd('mode', 'uniform') === 'random' ? 'random' : 'uniform';
            $res  = Services::calendar()->distribute(
                (int) $person->id,
                $y,
                $m,
                $targets,
                $mode,
                $this->input->post->getInt('granularity', 1),
                Services::projects(),
                (int) Acl::user()->id
            );

            $this->json([
                'success' => true,
                'message' => 'Distribuite ' . Time::format(array_sum($res['assigned'])) . ' ore (' . ($mode === 'random' ? 'casuale' : 'uniforme') . ').',
                'data'    => $this->monthPayload($person, $y, $m),
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /** POST: svuota ore del mese (tutte o di un progetto) */
    public function clear(): void
    {
        try {
            $this->requireToken();
            $person  = $this->targetPerson();
            [$y, $m] = $this->ym();
            $this->assertMonthEditable($y, $m);

            // target: "" = tutto, "p:<id>" = un progetto, "w:<id>" = un work package
            $target    = $this->input->post->getString('target', '');
            $projectId = $this->input->post->getInt('project') ?: null;
            $wpId      = null;

            if (preg_match('/^p:(\d+)$/', $target, $mm)) {
                $projectId = (int) $mm[1];
            } elseif (preg_match('/^w:(\d+)$/', $target, $mm)) {
                $wpId = (int) $mm[1];
            }

            $n = Services::calendar()->clearMonth((int) $person->id, $y, $m, $projectId, (int) Acl::user()->id, $wpId);

            $this->json(['success' => true, 'message' => "Rimosse $n registrazioni.", 'data' => $this->monthPayload($person, $y, $m)]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /** GET: elenco persone accessibili (selettore responsabile) */
    public function people(): void
    {
        try {
            $me = $this->me();
            $list = Services::people()->listVisible($me, $this->input->getString('q', ''), true);

            $this->json(['success' => true, 'data' => array_map(static fn ($p) => [
                'id'   => (int) $p->id,
                'name' => trim($p->cognome . ' ' . $p->nome),
                'uid'  => $p->uid,
                'sede' => $p->sede_nome,
            ], $list)]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /** POST: attiva/disattiva un progetto (interruttore della scheda progetto, salvataggio immediato) */
    public function toggleProject(): void
    {
        try {
            $this->requireToken();
            $this->requireManager();
            $id = $this->input->post->getInt('id');
            $on = $this->input->post->getBool('active');
            $p  = Services::projects()->get($id) ?? throw new \InvalidArgumentException('Progetto non trovato.');
            Services::projects()->setPublished($id, $on);
            $this->json(['success' => true, 'message' => $p->acronimo . ($on ? ': progetto attivo.' : ': progetto disattivato e archiviato.')]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /** POST: attiva/disattiva una persona (interruttore della scheda persona, salvataggio immediato) */
    public function togglePerson(): void
    {
        try {
            $this->requireToken();
            $this->requireManager();
            $me     = $this->me();
            $person = Services::people()->get($this->input->post->getInt('id'));

            if (!$person || !Services::people()->canAccess($me, $person)) {
                throw new \RuntimeException('Persona non gestibile.', 403);
            }

            $on = $this->input->post->getBool('active');
            Services::people()->saveProfile((int) $person->id, ['active' => $on ? 1 : 0], true);
            $this->json(['success' => true, 'message' => trim($person->cognome . ' ' . $person->nome) . ($on ? ': attivo.' : ': disattivato.')]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /** Interruttore "Presenze da ePAS" / "Inserimento manuale" della scheda persona. */
    public function togglePersonEpas(): void
    {
        try {
            $this->requireToken();
            $this->requireManager();
            $me     = $this->me();
            $person = Services::people()->get($this->input->post->getInt('id'));

            if (!$person || !Services::people()->canAccess($me, $person)) {
                throw new \RuntimeException('Persona non gestibile.', 403);
            }

            if (empty($person->matricola)) {
                throw new \DomainException('Senza matricola CNR le presenze si inseriscono sempre a mano.');
            }

            $on = $this->input->post->getBool('active');
            Services::people()->saveProfile((int) $person->id, ['epas_off' => $on ? 0 : 1], true);
            $person = Services::people()->get((int) $person->id);
            $reason = PeopleService::epasReason($person);
            $this->json(['success' => true, 'reason' => $reason, 'message' => trim($person->cognome . ' ' . $person->nome) . ': '
                . ($on ? ($reason ? 'ePAS attivato, ma ' . $reason . '.' : 'presenze lette da ePAS.') : 'presenze inserite a mano.')]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }
}
