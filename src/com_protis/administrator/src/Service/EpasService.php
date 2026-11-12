<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Crypto;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

/**
 * Integrazione ePAS (REST v3 "epas4clients") per il recupero di timbrature e assenze.
 */
final class EpasService extends AbstractService
{
    public const DEFAULT_URL = 'https://epas4clients.amministrazione.cnr.it/rest/v3';

    private function baseUrl(): string
    {
        $url = (string) ComponentHelper::getParams('com_protis')->get('epas_url', self::DEFAULT_URL);

        return rtrim($url !== '' ? $url : self::DEFAULT_URL, '/');
    }

    /** @return array{0:string,1:string}|null credenziali in chiaro per la sede della persona */
    public function credentialsFor(object $person): ?array
    {
        // collega di altro istituto: solo le credenziali della sede ePAS del suo istituto
        if (!empty($person->external)) {
            if (empty($person->epas_site_id)) {
                return null;
            }

            $r = $this->db->setQuery(
                $this->db->createQuery()->select('epas_user, epas_password')->from('#__protis_sites')
                    ->where('id = ' . (int) $person->epas_site_id)
            )->loadObject();

            $u = $r && $r->epas_user ? Crypto::decrypt($r->epas_user) : null;
            $p = $r && $r->epas_password ? Crypto::decrypt($r->epas_password) : null;

            return $u !== null && $p !== null ? [$u, $p] : null;
        }

        $q = $this->db->createQuery()->select('id, epas_user, epas_password')->from('#__protis_sites')
            ->where('epas_user IS NOT NULL')->where("epas_user <> ''")->where('epas_only = 0')
            ->order($person->site_id ? 'id = ' . (int) $person->site_id . ' DESC, id' : 'id');
        $rows = $this->db->setQuery($q)->loadObjectList();

        $fallback = (bool) ComponentHelper::getParams('com_protis')->get('epas_fallback_credentials', 1);

        foreach ($rows as $r) {
            if (!$fallback && (int) $r->id !== (int) $person->site_id) {
                continue;
            }

            $u = Crypto::decrypt($r->epas_user);
            $p = Crypto::decrypt($r->epas_password);

            if ($u !== null && $p !== null) {
                return [$u, $p];
            }
        }

        return null;
    }

    /** Chiamata grezza a ePAS: situazione mensile di una persona. */
    public function fetchMonth(string $matricola, int $year, int $month, array $credentials): array
    {
        $json = $this->get('/persondays/getmonthsituationbyperson?' . http_build_query([
            'number' => $matricola, 'year' => $year, 'month' => $month,
        ]), $credentials, 'per la matricola ' . $matricola);

        if (!isset($json['personDays'])) {
            throw new \RuntimeException('Risposta ePAS non valida.');
        }

        return $json;
    }

    /**
     * Elenco dei codici di assenza attivi e usati in ePAS (come "Scarica codici" della V.2).
     * Interroga ePAS con le credenziali di ogni sede e unisce i risultati.
     *
     * @return array<string,array{code:string,description:string,justified:int}> codice => dati
     */
    public function fetchAbsenceTypes(): array
    {
        $rows = $this->db->setQuery(
            $this->db->createQuery()->select('id, sede, epas_user, epas_password')->from('#__protis_sites')
                ->where('epas_user IS NOT NULL')->where("epas_user <> ''")->order('id')
        )->loadObjectList();

        $out    = [];
        $errors = [];
        $seen   = [];

        foreach ($rows as $r) {
            $u = Crypto::decrypt($r->epas_user);
            $p = Crypto::decrypt($r->epas_password);

            if ($u === null || $p === null || isset($seen[$u])) {
                continue;
            }

            $seen[$u] = true;

            try {
                foreach ($this->get('/absenceTypes/list?onlyActive=true&used=true', [$u, $p], 'per i codici di assenza') as $t) {
                    $code = strtoupper(trim((string) ($t['code'] ?? '')));

                    if ($code !== '' && !isset($out[$code])) {
                        $out[$code] = [
                            'code'        => $code,
                            'description' => trim((string) ($t['description'] ?? '')),
                            'justified'   => (int) ($t['justifiedTime'] ?? 0),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = $r->sede . ': ' . $e->getMessage();
            }
        }

        if (!$out) {
            throw new \RuntimeException($errors ? 'Download dei codici non riuscito. ' . implode(' | ', $errors) : 'Nessuna sede ha credenziali ePAS: impostarle in Impostazioni.');
        }

        return $out;
    }

    /** GET autenticato verso ePAS; restituisce il JSON decodificato. */
    private function get(string $path, array $credentials, string $what = ''): array
    {
        $url = $this->baseUrl() . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $credentials[0] . ':' . $credentials[1],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $app = Factory::getApplication();

        if ($app->get('proxy_enable')) {
            curl_setopt($ch, CURLOPT_PROXY, $app->get('proxy_host') . ':' . $app->get('proxy_port'));

            if ($app->get('proxy_user')) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $app->get('proxy_user') . ':' . $app->get('proxy_pass'));
            }
        }

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('ePAS non raggiungibile: ' . $err);
        }

        if ($code === 401 || $code === 403) {
            throw new \RuntimeException('ePAS: credenziali non valide o permessi insufficienti (HTTP ' . $code . ').');
        }

        if ($code >= 400) {
            throw new \RuntimeException('ePAS ha risposto con HTTP ' . $code . ($what !== '' ? ' ' . $what : '') . '.');
        }

        $json = json_decode((string) $body, true);

        if (!\is_array($json)) {
            throw new \RuntimeException('Risposta ePAS non valida.');
        }

        return $json;
    }

    /**
     * Converte un personDay ePAS nei campi di #__protis_days (stessa logica della V.2).
     */
    public static function mapPersonDay(array $day, array $codes): array
    {
        $minutes = 0;

        if (!empty($day['stampings']) && isset($day['timeAtWork']) && is_numeric($day['timeAtWork'])) {
            $minutes = (int) $day['timeAtWork'];
        } elseif (!empty($day['stampings'])) {
            $lastIn = null;

            foreach ($day['stampings'] as $s) {
                $t = strtotime(substr((string) $s['date'], 0, 16));

                if (($s['way'] ?? '') === 'in') {
                    $lastIn = $t;
                } elseif (($s['way'] ?? '') === 'out' && $lastIn) {
                    $minutes += (int) (($t - $lastIn) / 60);
                    $lastIn = null;
                }
            }

            if ((int) ($day['decurtedMeal'] ?? 0) === 30) {
                $minutes -= 30;
            }
        }

        $row = [
            'worked_min'    => max(0, $minutes),
            'smart_min'     => 0,
            'tele_min'      => 0,
            'mission_min'   => 0,
            'sick_min'      => 0,
            'holiday_min'   => 0,
            'permit_min'    => 0,
            'other_min'     => 0,
            'absence_codes' => '',
            'blocked'       => 0,
        ];

        $list = [];

        foreach ($day['absences'] ?? [] as $a) {
            $code = strtoupper(trim((string) ($a['code'] ?? '')));

            if ($code === '') {
                continue;
            }

            $list[] = $code;
            $info   = $codes[$code] ?? null;

            if (!$info) {
                continue;
            }

            if ($info->blocks_fill) {
                $row['blocked'] = 1;
            }

            $col = CalendarService::CATEGORIES[$info->category] ?? null;

            // come in V.2: per ogni categoria vale l'orario convenzionale del codice (non si somma)
            if ($col && $info->minutes !== null) {
                $row[$col] = max($row[$col], (int) $info->minutes);
            }
        }

        $row['absence_codes'] = substr(implode(',', array_unique($list)), 0, 255);

        return $row;
    }

    /**
     * Sincronizza un mese per una persona con matricola.
     *
     * @return array{days:int, worked:int}
     */
    public function syncMonth(object $person, int $year, int $month, CalendarService $calendar): array
    {
        if (!PeopleService::usesEpas($person)) {
            throw new \DomainException('Sincronizzazione ePAS non attiva: ' . PeopleService::epasReason($person) . '.');
        }

        if (\sprintf('%04d%02d', $year, $month) > date('Ym')) {
            throw new \DomainException('Non è possibile sincronizzare un mese futuro.');
        }

        $cred = $this->credentialsFor($person);

        if (!$cred) {
            throw new \DomainException('Credenziali ePAS non configurate per la sede (Impostazioni → Sedi e ePAS).');
        }

        $json   = $this->fetchMonth((string) $person->matricola, $year, $month, $cred);
        $codes  = $calendar->absenceCodes();
        $count  = 0;
        $worked = 0;

        foreach ($json['personDays'] as $pd) {
            $date = substr((string) ($pd['date'] ?? ''), 0, 10);

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $row = self::mapPersonDay($pd, $codes) + [
                'person_id' => (int) $person->id,
                'day'       => $date,
                'source'    => 'epas',
                'epas_raw'  => json_encode($pd, JSON_UNESCAPED_UNICODE),
                'synced'    => $this->now(),
                'modified'  => $this->now(),
            ];

            $this->upsert('#__protis_days', $row, array_values(array_diff(array_keys($row), ['person_id', 'day'])));
            $count++;
            $worked += $row['worked_min'];
        }

        $this->log('epas.sync', \sprintf('Sincronizzati %d giorni %02d/%d', $count, $month, $year), (int) $person->id);

        return ['days' => $count, 'worked' => $worked];
    }

    /** Elenco persone attive con matricola (per la sincronizzazione notturna). @return object[] */
    public function syncablePeople(): array
    {
        $q = $this->db->createQuery()->select('p.*, s.codice_sede')->from('#__protis_people AS p')
            ->join('LEFT', '#__protis_sites AS s', 's.id = p.site_id')
            ->where('p.active = 1')->where('p.epas_off = 0')->where('(p.external = 0 OR p.epas_site_id IS NOT NULL)')
            ->where('p.matricola IS NOT NULL')->where("p.matricola <> ''")
            ->order('p.cognome');

        return $this->db->setQuery($q)->loadObjectList();
    }
}
