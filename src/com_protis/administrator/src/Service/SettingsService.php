<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Crypto;
use Cnr\Component\Protis\Administrator\Helper\Time;

/**
 * Sedi + credenziali ePAS, codici di assenza.
 */
final class SettingsService extends AbstractService
{
    public function saveSite(array $d): int
    {
        $id  = (int) ($d['id'] ?? 0);
        $obj = (object) [
            'ente'        => trim((string) ($d['ente'] ?? 'CNR')),
            'istituto'    => trim((string) ($d['istituto'] ?? '')),
            'sigla'       => trim((string) ($d['sigla'] ?? '')),
            'sede'        => trim((string) ($d['sede'] ?? '')),
            'codice_sede' => trim((string) ($d['codice_sede'] ?? '')),
            'published'   => isset($d['published']) ? (int) (bool) $d['published'] : 1,
            'epas_only'   => (int) !empty($d['epas_only']),
            'modified'    => $this->now(),
        ];

        if ($obj->sede === '' || !preg_match('/^\d{3,10}$/', $obj->codice_sede)) {
            throw new \InvalidArgumentException('Sede e codice sede ePAS (numerico) sono obbligatori.');
        }

        // le credenziali si aggiornano solo se compilate (i campi password non vengono mai rimostrati)
        if (trim((string) ($d['epas_user'] ?? '')) !== '') {
            $obj->epas_user = Crypto::encrypt(trim((string) $d['epas_user']));
        }

        if ((string) ($d['epas_password'] ?? '') !== '') {
            $obj->epas_password = Crypto::encrypt((string) $d['epas_password']);
        }

        $dup = (int) $this->db->setQuery(
            $this->db->createQuery()->select('id')->from('#__protis_sites')
                ->where('codice_sede = ' . $this->db->quote($obj->codice_sede))->where('id <> ' . $id)
        )->loadResult();

        if ($dup) {
            throw new \InvalidArgumentException('Esiste già una sede con codice ' . $obj->codice_sede . '.');
        }

        if ($id) {
            $obj->id = $id;
            $this->db->updateObject('#__protis_sites', $obj, 'id');
        } else {
            $obj->created = $this->now();
            $this->db->insertObject('#__protis_sites', $obj, 'id');
            $id = (int) $obj->id;
        }

        $this->log('site.save', 'Sede ' . $obj->sede . ' salvata');

        return $id;
    }

    public function deleteSite(int $id): void
    {
        $used = (int) $this->db->setQuery('SELECT COUNT(*) FROM #__protis_people WHERE site_id = ' . $id)->loadResult();

        if ($used) {
            throw new \DomainException("Sede in uso da $used persone: disattivarla invece di eliminarla.");
        }

        $this->db->setQuery('DELETE FROM #__protis_sites WHERE id = ' . $id)->execute();
    }

    /** @return object[] */
    public function absenceCodes(string $search = ''): array
    {
        $q = $this->db->createQuery()->select('*')->from('#__protis_absence_codes')->order('is_new DESC, category, code');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where('(code LIKE :a OR description LIKE :b)')->bind(':a', $like)->bind(':b', $like);
        }

        return $this->db->setQuery($q)->loadObjectList();
    }

    public function saveAbsenceCode(array $d): void
    {
        $code = strtoupper(trim((string) ($d['code'] ?? '')));

        if ($code === '') {
            throw new \InvalidArgumentException('Codice obbligatorio.');
        }

        $category = (string) ($d['category'] ?? 'altro');

        if (!\array_key_exists($category, CalendarService::CATEGORIES)) {
            $category = 'altro';
        }

        $data = [
            'code'        => $code,
            'description' => trim((string) ($d['description'] ?? '')),
            'category'    => $category,
            'minutes'     => trim((string) ($d['minutes'] ?? '')) !== '' ? Time::toMinutes((string) $d['minutes']) : null,
            'blocks_fill' => !empty($d['blocks_fill']) ? 1 : 0,
            'is_new'      => 0,
            'modified'    => $this->now(),
        ];

        if (!empty($d['id'])) {
            $obj     = (object) $data;
            $obj->id = (int) $d['id'];
            $this->db->updateObject('#__protis_absence_codes', $obj, 'id', true);
        } else {
            $this->upsert('#__protis_absence_codes', $data, ['description', 'category', 'minutes', 'blocks_fill', 'modified']);
        }

        $this->log('code.save', 'Codice assenza ' . $code . ' salvato');
    }

    /**
     * Inserisce i codici scaricati da ePAS che non sono ancora censiti.
     * I nuovi codici sono marcati "nuovo" (da completare con categoria e orario) e, per prudenza,
     * esclusi da Riempi/Distribuisci finché non vengono rivisti.
     *
     * @param  array<string,array{code:string,description:string,justified:int}> $types
     * @return string[] codici nuovi
     */
    public function importAbsenceTypes(array $types): array
    {
        $existing = array_flip(array_map('strtoupper', $this->db->setQuery(
            $this->db->createQuery()->select('code')->from('#__protis_absence_codes')
        )->loadColumn()));
        $new = [];

        foreach ($types as $code => $t) {
            if (isset($existing[$code])) {
                // la descrizione è quella di ePAS
                if ($t['description'] !== '') {
                    $this->db->setQuery(
                        $this->db->createQuery()->update('#__protis_absence_codes')
                            ->set('description = ' . $this->db->quote($t['description']))
                            ->where('code = ' . $this->db->quote($code))
                    )->execute();
                }
                continue;
            }

            $obj = (object) [
                'code'        => mb_substr($code, 0, 20),
                'description' => $t['description'],
                'category'    => 'altro',
                'minutes'     => null,
                'blocks_fill' => 1,
                'is_new'      => 1,
                'modified'    => $this->now(),
            ];
            $this->db->insertObject('#__protis_absence_codes', $obj);
            $new[] = $code;
        }

        $this->log('code.download', 'Codici ePAS scaricati: ' . \count($types) . ', nuovi: ' . ($new ? implode(', ', $new) : 'nessuno'));

        return $new;
    }

    /** Ultimo scaricamento dei codici da ePAS (dal registro operazioni). */
    public function lastCodesDownload(): ?object
    {
        $q = $this->db->createQuery()
            ->select('l.created, l.message, u.name AS user_name')
            ->from('#__protis_log AS l')
            ->join('LEFT', '#__users AS u', 'u.id = l.user_id')
            ->where("l.action = 'code.download'")
            ->order('l.id DESC');

        return $this->db->setQuery($q, 0, 1)->loadObject() ?: null;
    }

    /** Salvataggio di tutta la tabella dei codici (categoria, orario, esclusione). */
    public function saveAbsenceCodes(array $rows): int
    {
        $n = 0;

        foreach ($rows as $id => $d) {
            $category = (string) ($d['category'] ?? 'altro');

            $obj = (object) [
                'id'          => (int) $id,
                'category'    => \array_key_exists($category, CalendarService::CATEGORIES) ? $category : 'altro',
                'minutes'     => trim((string) ($d['minutes'] ?? '')) !== '' ? Time::toMinutes((string) $d['minutes']) : null,
                'blocks_fill' => !empty($d['blocks_fill']) ? 1 : 0,
                'is_new'      => 0,
                'modified'    => $this->now(),
            ];
            $this->db->updateObject('#__protis_absence_codes', $obj, 'id', true);
            $n++;
        }

        $this->log('code.save', 'Codici di assenza aggiornati: ' . $n);

        return $n;
    }

    /** @return object[] ultime operazioni */
    public function recentLog(int $limit = 100, ?int $personId = null): array
    {
        $q = $this->db->createQuery()
            ->select('l.*, u.name AS user_name, CONCAT(p.cognome, \' \', p.nome) AS person_name')
            ->from('#__protis_log AS l')
            ->join('LEFT', '#__users AS u', 'u.id = l.user_id')
            ->join('LEFT', '#__protis_people AS p', 'p.id = l.person_id')
            ->order('l.id DESC');

        if ($personId) {
            $q->where('l.person_id = ' . $personId);
        }

        return $this->db->setQuery($q, 0, $limit)->loadObjectList();
    }
}
