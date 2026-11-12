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
use Cnr\Component\Protis\Administrator\Service\Services;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Azioni dei form di gestione (POST + redirect).
 */
class ManageController extends BaseController
{
    use ProtisControllerTrait;

    private function done(string $view, string $message, string $extra = ''): void
    {
        $this->setRedirect(Route::_('index.php?option=com_protis&view=' . $view . $extra, false), $message);
    }

    private function error(string $view, \Throwable $e, string $extra = ''): void
    {
        $this->setRedirect(Route::_('index.php?option=com_protis&view=' . $view . $extra, false), $e->getMessage(), 'error');
    }

    /* ---------------- Progetti ---------------- */

    public function saveProject(): void
    {
        $id = $this->input->post->getInt('id');

        try {
            $this->requireToken();
            $this->requireManager();

            $data = $this->input->post->get('jform', [], 'array');
            $data['id'] = $id;
            $wps     = (array) $this->input->post->get('wps', [], 'array');
            $logos   = array_values((array) $this->input->post->get('logos', [], 'array'));
            $uploads = $this->input->files->get('logo_files', [], 'raw');
            $uploads = isset($uploads['name']) ? [$uploads] : array_values((array) $uploads);
            $header  = $this->input->post->get('header', null, 'array');

            $id = Services::projects()->save(
                $data,
                array_values($wps),
                $logos,
                $uploads,
                \is_array($header) ? array_values($header) : null,
                (int) Acl::user()->id
            );
            $this->done('project', 'Progetto salvato.', '&id=' . $id);
        } catch (\Throwable $e) {
            $this->error('project', $e, $id ? '&id=' . $id : '');
        }
    }

    public function deleteProject(): void
    {
        try {
            $this->requireToken();
            $this->requireManager();
            if (!Acl::isSuperUser()) {
                throw new \RuntimeException('Solo il super utente può eliminare o archiviare un progetto da qui.', 403);
            }

            Services::projects()->delete($this->input->post->getInt('id'));
            $this->done('projects', 'Operazione completata (i progetti con ore registrate vengono archiviati).');
        } catch (\Throwable $e) {
            $this->error('projects', $e);
        }
    }

    /* ---------------- Associazioni ---------------- */

    public function saveAssignment(): void
    {
        $back = $this->input->post->getString('back', 'assignments');
        $back = \in_array($back, ['assignments', 'person', 'project'], true) ? $back : 'assignments';
        $extra = $this->input->post->getString('back_extra', '');
        $extra = preg_match('/^(&[a-z_]+=\d+)*$/', $extra) ? $extra : '';

        try {
            $this->requireToken();

            $me       = $this->me();
            $personIds = array_map('intval', (array) $this->input->post->get('person_id', [], 'array'));
            $projectId = $this->input->post->getInt('project_id');

            if (!$personIds || !$projectId) {
                throw new \InvalidArgumentException('Selezionare persona e progetto.');
            }

            // gli utenti si associano da soli, solo alla propria utenza e solo a progetti attivi
            if (!Acl::canManage()) {
                $project = Services::projects()->get($projectId);

                if (!$me || $personIds !== [(int) $me->id]) {
                    throw new \RuntimeException('Puoi associare ai progetti solo la tua utenza.', 403);
                }

                if (!$project || empty($project->published)) {
                    throw new \RuntimeException('Progetto non disponibile.', 403);
                }
            }

            // ore previste e monte ore annuo per persona (monteore[ID], monte_annuo[ID]) oppure unici
            $hours   = $this->input->post->get('monteore', null, 'raw');
            $annual  = $this->input->post->get('monte_annuo', null, 'raw');
            $edit    = $this->input->post->getBool('edit');
            $saved   = 0;
            $already = [];

            foreach ($personIds as $pid) {
                $person = Services::people()->get($pid);

                if (!$person || !Services::people()->canAccess($me, $person)) {
                    throw new \RuntimeException('Persona non gestibile: ' . $pid, 403);
                }

                // nuova associazione: se esiste già non si tocca nulla (il monte ore si cambia dall'elenco)
                if (!$edit && Services::projects()->assignmentExists($pid, $projectId)) {
                    $already[] = trim($person->cognome . ' ' . $person->nome);
                    continue;
                }

                $saved++;
                $pick = static fn ($v) => \is_array($v) ? (string) ($v[$pid] ?? '') : ($v === null ? null : (string) $v);

                if ($edit) {
                    // modifica in linea: solo il campo inviato
                    foreach (['monteore' => $hours, 'monte_annuo' => $annual] as $f => $v) {
                        if ($v !== null) {
                            Services::projects()->updateAssignmentHours($pid, $projectId, $f, $pick($v));
                        }
                    }

                    continue;
                }

                Services::projects()->saveAssignment($pid, $projectId, $pick($hours), $pick($annual));
            }

            if ($back === 'assignments' && !$edit) {
                $extra = '&project=' . $projectId; // mostra subito l'elenco del progetto appena associato
            }

            if ($edit) {
                $this->done($back, 'Ore aggiornate.', $extra);

                return;
            }

            $msg = $saved ? ($saved > 1 ? $saved . ' associazioni salvate.' : 'Associazione salvata.') : '';

            if ($already) {
                $acr  = Services::projects()->get($projectId)->acronimo ?? '';
                $msg .= ($msg ? ' ' : '') . (\count($already) > 1
                    ? 'Già associate a ' . $acr . ', non modificate: ' . implode(', ', $already) . '.'
                    : implode('', $already) . ' è già associata a ' . $acr . ': associazione non modificata.')
                    . ' Per cambiare il monte ore correggilo nell\'elenco delle associazioni.';
                $this->setRedirect(Route::_('index.php?option=com_protis&view=' . $back . $extra, false), $msg, 'warning');

                return;
            }

            $this->done($back, $msg, $extra);
        } catch (\Throwable $e) {
            $this->error($back, $e, $extra);
        }
    }

    /** Abilita colleghi di altri istituti (UID LDAP) e, se indicato, li associa a un progetto. */
    public function addExternal(): void
    {
        $back      = $this->input->post->getString('back', 'people') === 'project' ? 'project' : 'people';
        $projectId = $this->input->post->getInt('project_id');
        $extra     = $back === 'project' && $projectId ? '&id=' . $projectId : '';

        try {
            $this->requireToken();
            $this->requireManager();

            $uids = preg_split('/[\s,;]+/', strtolower($this->input->post->getString('uids', '')), -1, PREG_SPLIT_NO_EMPTY);

            if (!$uids) {
                throw new \InvalidArgumentException('Indicare almeno uno username (UID) CNR, es. nome.cognome');
            }

            $ids   = Services::people()->enableExternal($uids);
            $names = [];

            foreach ($ids as $uid => $pid) {
                if ($projectId) {
                    Services::projects()->saveAssignment($pid, $projectId, $this->input->post->getString('monteore'));
                }

                $p       = Services::people()->get($pid);
                $names[] = trim($p->cognome . ' ' . $p->nome) ?: $uid . ' (nome al primo accesso)';
            }

            $skipped = \count($uids) - \count($ids);
            $this->done($back, 'Abilitati: ' . implode(', ', $names) . ($projectId ? ' – associati al progetto.' : '.')
                . ($skipped ? " $skipped UID non validi ignorati." : ''), $extra);
        } catch (\Throwable $e) {
            $this->error($back, $e, $extra);
        }
    }

    public function deleteAssignment(): void
    {
        $back  = $this->input->post->getString('back', 'assignments');
        $back  = \in_array($back, ['assignments', 'person', 'project'], true) ? $back : 'assignments';
        $extra = $this->input->post->getString('back_extra', '');
        $extra = preg_match('/^(&[a-z_]+=\d+)*$/', $extra) ? $extra : '';

        try {
            $this->requireToken();
            $id = $this->input->post->getInt('id');

            // un utente può togliere solo una propria associazione su cui non ha ancora rendicontato ore
            if (!Acl::canManage()) {
                $me = $this->me();
                $a  = Services::projects()->getAssignment($id);

                if (!$me || !$a || (int) $a->person_id !== (int) $me->id) {
                    throw new \RuntimeException('Associazione non tua.', 403);
                }

                if (Services::projects()->reportedOnProject((int) $me->id, (int) $a->project_id) > 0) {
                    throw new \DomainException('Hai già rendicontato ore su questo progetto: per togliere l\'associazione rivolgiti al responsabile.');
                }
            }

            Services::projects()->deleteAssignment($id);
            $this->done($back, 'Associazione rimossa (le ore già registrate restano nel calendario).', $extra);
        } catch (\Throwable $e) {
            $this->error($back, $e, $extra);
        }
    }

    /* ---------------- Persone / profilo ---------------- */

    public function saveProfile(): void
    {
        try {
            $this->requireToken();
            $me = $this->me() ?? throw new \RuntimeException('Accesso richiesto', 401);
            Services::people()->saveProfile((int) $me->id, $this->input->post->get('jform', [], 'array'), false);
            $this->done('profile', 'Profilo aggiornato.');
        } catch (\Throwable $e) {
            $this->error('profile', $e);
        }
    }

    /** Foto del profilo dell'utente collegato. */
    public function saveAvatar(): void
    {
        try {
            $this->requireToken();
            $me = $this->me() ?? throw new \RuntimeException('Accesso richiesto', 401);

            if ($this->input->post->getBool('remove')) {
                Services::people()->removeAvatar((int) $me->id);
                $this->done('profile', 'Foto rimossa.');

                return;
            }

            Services::people()->saveAvatar((int) $me->id, (array) $this->input->files->get('avatar', [], 'raw'));
            $this->done('profile', 'Foto del profilo aggiornata.');
        } catch (\Throwable $e) {
            $this->error('profile', $e);
        }
    }

    public function savePerson(): void
    {
        $id = $this->input->post->getInt('id');

        try {
            $this->requireToken();
            $this->requireManager();
            $me   = $this->me();
            $data = $this->input->post->get('jform', [], 'array');

            if ($id) {
                $person = Services::people()->get($id);

                if (!$person || !Services::people()->canAccess($me, $person)) {
                    throw new \RuntimeException('Non autorizzato', 403);
                }

                if (!Acl::canManageAllSites() && !empty($data['site_id']) && (int) $data['site_id'] !== (int) $me->site_id) {
                    throw new \DomainException('Puoi assegnare solo la tua sede.');
                }

                Services::people()->saveProfile($id, $data, true);
            } else {
                if (!Acl::canManageAllSites()) {
                    $data['site_id'] = $me->site_id;
                }

                $id = Services::people()->createManual($data);
            }

            $this->done('person', 'Persona salvata.', '&id=' . $id);
        } catch (\Throwable $e) {
            $this->error($id ? 'person' : 'people', $e, $id ? '&id=' . $id : '');
        }
    }

    /* ---------------- Impostazioni (admin) ---------------- */

    public function saveSite(): void
    {
        try {
            $this->requireToken();

            if (!Acl::isAdmin()) {
                throw new \RuntimeException('Riservato agli amministratori.', 403);
            }

            Services::settings()->saveSite($this->input->post->get('jform', [], 'array'));
            $this->done('settings', 'Sede salvata.');
        } catch (\Throwable $e) {
            $this->error('settings', $e);
        }
    }

    public function deleteSite(): void
    {
        try {
            $this->requireToken();

            if (!Acl::isAdmin()) {
                throw new \RuntimeException('Riservato agli amministratori.', 403);
            }

            Services::settings()->deleteSite($this->input->post->getInt('id'));
            $this->done('settings', 'Sede eliminata.');
        } catch (\Throwable $e) {
            $this->error('settings', $e);
        }
    }

    public function testEpas(): void
    {
        $back = Route::_('index.php?option=com_protis&view=settings', false) . '#pt-epas-test';
        $pid  = $this->input->post->getInt('person_id');

        try {
            $this->requireToken();

            if (!Acl::isAdmin()) {
                throw new \RuntimeException('Riservato agli amministratori.', 403);
            }

            $person = Services::people()->get($pid);

            if (!$person) {
                throw new \InvalidArgumentException('Selezionare una persona con matricola.');
            }

            $res = Services::epas()->syncMonth($person, (int) date('Y'), (int) date('n'), Services::calendar());
            $msg = \sprintf('Connessione ePAS riuscita: %d giorni letti per %s %s (%s).', $res['days'], $person->nome, $person->cognome, Time::monthName((int) date('n')) . ' ' . date('Y'));
            $this->app->setUserState('com_protis.epastest', ['type' => 'ok', 'text' => $msg, 'person' => $pid]);
        } catch (\Throwable $e) {
            $this->app->setUserState('com_protis.epastest', ['type' => 'danger', 'text' => 'Test ePAS non riuscito: ' . $e->getMessage(), 'person' => $pid]);
        }

        // l'esito si mostra nel riquadro "Test connessione ePAS", non in cima alla pagina
        $this->setRedirect($back);
    }

    public function syncAll(): void
    {
        try {
            $this->requireToken();
            $this->requireManager();
            @set_time_limit(600);

            $me     = $this->me();
            $y      = $this->input->post->getInt('year', (int) date('Y'));
            $m      = $this->input->post->getInt('month', (int) date('n'));
            $ok     = 0;
            $errors = [];

            foreach (Services::epas()->syncablePeople() as $p) {
                if (!Services::people()->canAccess($me, $p)) {
                    continue;
                }

                try {
                    Services::epas()->syncMonth($p, $y, $m, Services::calendar());
                    $ok++;
                } catch (\Throwable $e) {
                    $errors[] = $p->cognome . ': ' . $e->getMessage();
                }
            }

            $msg = "Sincronizzate $ok persone per $m/$y." . ($errors ? ' Errori: ' . implode(' | ', \array_slice($errors, 0, 5)) : '');
            $this->setRedirect(Route::_('index.php?option=com_protis&view=people', false), $msg, $errors ? 'warning' : 'message');
        } catch (\Throwable $e) {
            $this->error('people', $e);
        }
    }

    public function saveCode(): void
    {
        try {
            $this->requireToken();
            $this->requireManager();
            Services::settings()->saveAbsenceCode($this->input->post->get('jform', [], 'array'));
            $this->done('codes', 'Codice salvato.');
        } catch (\Throwable $e) {
            $this->error('codes', $e);
        }
    }

    /** Scarica da ePAS l'elenco dei codici di assenza e segnala quelli nuovi. */
    public function downloadCodes(): void
    {
        try {
            $this->requireToken();
            $this->requireManager();
            $types = Services::epas()->fetchAbsenceTypes();
            $new   = Services::settings()->importAbsenceTypes($types);

            if ($new) {
                $this->setRedirect(
                    Route::_('index.php?option=com_protis&view=codes', false),
                    \sprintf('%s da ePAS: %s. Sono evidenziati in verde in cima all\'elenco: imposta categoria e orario e premi "Salva modifiche".',
                        \count($new) === 1 ? 'Trovato 1 codice nuovo' : 'Trovati ' . \count($new) . ' codici nuovi', implode(', ', $new)),
                    'warning'
                );

                return;
            }

            $this->done('codes', \sprintf('Codici scaricati da ePAS (%d): nessun codice nuovo.', \count($types)));
        } catch (\Throwable $e) {
            $this->error('codes', $e);
        }
    }

    public function saveCodes(): void
    {
        try {
            $this->requireToken();
            $this->requireManager();
            $n = Services::settings()->saveAbsenceCodes((array) $this->input->post->get('codes', [], 'array'));
            $this->done('codes', $n === 1 ? 'Codice salvato.' : $n . ' codici salvati.');
        } catch (\Throwable $e) {
            $this->error('codes', $e);
        }
    }
}
