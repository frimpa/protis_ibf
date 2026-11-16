<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\Controller;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Acl;
use Cnr\Component\Protis\Administrator\Service\Services;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Session\Session;

trait ProtisControllerTrait
{
    protected function me(): ?object
    {
        $me = Services::people()->forUser(Acl::user());

        // persona disattivata: per PROTIS IBF è come non collegata (il super utente resta sempre attivo)
        return $me && isset($me->active) && !(int) $me->active && !Acl::isSuperUser() ? null : $me;
    }

    /** Persona richiesta (parametro person) se l'utente può accedervi, altrimenti sé stesso. */
    protected function targetPerson(bool $strict = true): object
    {
        $me = $this->me();

        if (!$me) {
            throw new \RuntimeException('Accesso richiesto.', 401);
        }

        $id = $this->input->getInt('person', 0);

        if (!$id || $id === (int) $me->id) {
            return $me;
        }

        $person = Services::people()->get($id);

        if (!$person || !Services::people()->canAccess($me, $person)) {
            if ($strict) {
                throw new \RuntimeException('Non autorizzato ad accedere al calendario di questa persona.', 403);
            }

            return $me;
        }

        return $person;
    }

    protected function json(array $data, int $status = 200): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);

        if ($status !== 200) {
            $this->app->setHeader('Status', (string) $status, true);
        }

        $this->app->sendHeaders();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->app->close();
    }

    protected function fail(\Throwable $e): void
    {
        $code = (int) $e->getCode();
        $code = $code >= 400 && $code < 600 ? $code : 400;

        if (!$e instanceof \DomainException && !$e instanceof \InvalidArgumentException && $code < 401) {
            // errore imprevisto: non esporre dettagli interni
            \Joomla\CMS\Log\Log::add($e->getMessage() . "\n" . $e->getTraceAsString(), \Joomla\CMS\Log\Log::ERROR, 'com_protis');
        }

        $this->json(['success' => false, 'message' => $e->getMessage()], $code);
    }

    protected function requireToken(): void
    {
        if (!Session::checkToken('post') && !Session::checkToken('get')) {
            throw new \RuntimeException('Sessione scaduta: ricaricare la pagina.', 403);
        }
    }

    protected function requireManager(): void
    {
        if (!Acl::canManage()) {
            throw new \RuntimeException('Funzione riservata ai responsabili.', 403);
        }
    }

    /** Mese bloccato per l'utente corrente? (opzione "Blocca modifiche dopo N giorni") */
    protected function assertMonthEditable(int $year, int $month): void
    {
        $days = (int) ComponentHelper::getParams('com_protis')->get('lock_days', 0);

        if ($days <= 0 || Acl::canManage()) {
            return;
        }

        $end = strtotime(date('Y-m-t 23:59:59', strtotime(\sprintf('%04d-%02d-01', $year, $month))));

        if (time() > $end + $days * 86400) {
            throw new \DomainException('Il mese è chiuso: rivolgersi al responsabile per modifiche.');
        }
    }
}
