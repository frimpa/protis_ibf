<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;
use Joomla\Filesystem\File;
use Joomla\Database\ParameterType;

final class ProjectService extends AbstractService
{
    public const LAYOUTS = [
        'standard' => 'Standard (italiano)',
        'horizon'  => 'Horizon / Grant Agreement (inglese)',
        'mise'     => 'Decreto ministeriale / MISE (inglese)',
        'sal'      => 'SAL con P.IVA e monte ore annuo (italiano)',
    ];

    /** Cartella loghi: images/protis/loghi */
    public static function logoDir(): string
    {
        return JPATH_ROOT . '/images/protis/loghi';
    }

    public static function logoUrl(string $logo): string
    {
        return $logo !== '' ? 'images/protis/loghi/' . rawurlencode($logo) : '';
    }

    /** @return object[] */
    public function list(bool $onlyPublished = false, string $search = ''): array
    {
        $q = $this->db->createQuery()
            ->select('p.*')
            ->select('(SELECT COUNT(*) FROM #__protis_workpackages w WHERE w.project_id = p.id) AS n_wp')
            ->select('(SELECT COUNT(*) FROM #__protis_assignments a WHERE a.project_id = p.id) AS n_persone')
            ->from('#__protis_projects AS p')
            ->order('p.published DESC, p.acronimo');

        if ($onlyPublished) {
            $q->where('p.published = 1');
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where('(p.acronimo LIKE :s1 OR p.cup LIKE :s2 OR p.titolo LIKE :s3 OR p.grant_agreement LIKE :s4)')
                ->bind(':s1', $like)->bind(':s2', $like)->bind(':s3', $like)->bind(':s4', $like);
        }

        return $this->db->setQuery($q)->loadObjectList();
    }

    public function get(int $id): ?object
    {
        $q = $this->db->createQuery()->select('*')->from('#__protis_projects')
            ->where('id = :id')->bind(':id', $id, ParameterType::INTEGER);
        $p = $this->db->setQuery($q)->loadObject();

        if (!$p) {
            return null;
        }

        $p->wps = $this->workpackages($id);

        return $p;
    }

    /** @return object[] */
    public function workpackages(int $projectId, bool $onlyPublished = false): array
    {
        $q = $this->db->createQuery()->select('*')->from('#__protis_workpackages')
            ->where('project_id = :p')->bind(':p', $projectId, ParameterType::INTEGER)
            ->order('ordering, id');

        if ($onlyPublished) {
            $q->where('published = 1');
        }

        return $this->db->setQuery($q)->loadObjectList();
    }

    /**
     * Salva progetto + WP.
     *
     * @param  array  $wps  [['id'=>?, 'label'=>..., 'published'=>1], ...]
     */
    public function save(array $data, array $wps, array $logos = [], array $uploads = [], ?array $headerRows = null, int $userId = 0): int
    {
        $id  = (int) ($data['id'] ?? 0);
        $obj = (object) [
            'acronimo'        => trim((string) ($data['acronimo'] ?? '')),
            'titolo'          => trim((string) ($data['titolo'] ?? '')),
            'codice_avviso'   => trim((string) ($data['codice_avviso'] ?? '')),
            'cup'             => strtoupper(preg_replace('/\s+/', '', (string) ($data['cup'] ?? ''))),
            'grant_agreement' => preg_replace('/\s+/', '', (string) ($data['grant_agreement'] ?? '')),
            'ente'            => trim((string) ($data['ente'] ?? '')),
            'codice_progetto' => trim((string) ($data['codice_progetto'] ?? '')),
            'note'            => trim((string) ($data['note'] ?? '')),
            'tipo_ricerca'    => mb_substr(trim((string) (($data['tipo_ricerca'] ?? '') === 'altro' ? ($data['tipo_ricerca_altro'] ?? '') : ($data['tipo_ricerca'] ?? ''))), 0, 150),
            'data_inizio'     => $this->dateOrNull($data['data_inizio'] ?? null),
            'data_fine'       => $this->dateOrNull($data['data_fine'] ?? null),
            'data_proroga'    => $this->dateOrNull($data['data_proroga'] ?? null),
            'layout'          => \array_key_exists($data['layout'] ?? '', self::LAYOUTS) ? $data['layout'] : 'standard',
            'partita_iva'     => trim((string) ($data['partita_iva'] ?? '')),
            'sal_numero'      => trim((string) ($data['sal_numero'] ?? '')),
            'monte_ore_annuo' => trim((string) ($data['monte_ore_annuo'] ?? '')),
            'site_id'         => ctype_digit((string) ($data['site_id'] ?? '')) && (int) $data['site_id'] > 0 ? (int) $data['site_id'] : null,
            'sede_altro'      => ($data['site_id'] ?? '') === 'altro' ? trim((string) ($data['sede_altro'] ?? '')) : '',
            'published'       => isset($data['published']) ? (int) (bool) $data['published'] : 1,
            'table_lang'      => \in_array($data['table_lang'] ?? '', ['it', 'en'], true) ? $data['table_lang'] : '',
            'modified'        => $this->now(),
        ];

        if ($headerRows !== null) {
            $rows = HeaderTemplate::decode(json_encode(array_values($headerRows)));
            $obj->header_fields = $rows ? json_encode($rows, JSON_UNESCAPED_UNICODE) : null;
        }

        if ($obj->acronimo === '') {
            throw new \InvalidArgumentException("L'acronimo è obbligatorio.");
        }

        if ($id) {
            $obj->id = $id;
            $this->db->updateObject('#__protis_projects', $obj, 'id', true);
        } else {
            $obj->created    = $this->now();
            $obj->created_by = $userId;
            $this->db->insertObject('#__protis_projects', $obj, 'id');
            $id = (int) $obj->id;
        }

        $this->saveLogos($id, $logos, $uploads);

        $this->saveWorkpackages($id, $wps);
        $this->log('project.save', 'Progetto ' . $obj->acronimo . ' salvato');

        return $id;
    }

    private function saveWorkpackages(int $projectId, array $wps): void
    {
        $keep     = [];
        $ordering = 0;

        foreach ($wps as $wp) {
            $label = trim((string) ($wp['label'] ?? ''));
            $wpId  = (int) ($wp['id'] ?? 0);

            if ($label === '' && !$wpId) {
                continue;
            }

            $ordering++;
            $row = (object) [
                'project_id' => $projectId,
                'label'      => $label !== '' ? $label : 'WP ' . $ordering,
                'ordering'   => $ordering,
                'published'  => isset($wp['published']) ? (int) (bool) $wp['published'] : 1,
            ];

            if ($wpId) {
                $row->id = $wpId;
                $this->db->updateObject('#__protis_workpackages', $row, 'id');
            } else {
                $row->code = 'wp_' . $ordering;
                $this->db->insertObject('#__protis_workpackages', $row, 'id');
                $wpId = (int) $row->id;
            }

            $keep[] = $wpId;
        }

        // I WP rimossi dal form vengono disattivati (non cancellati) se hanno ore registrate
        $q = $this->db->createQuery()->select('w.id, (SELECT COUNT(*) FROM #__protis_hours h WHERE h.wp_id = w.id) AS n')
            ->from('#__protis_workpackages AS w')->where('w.project_id = ' . $projectId);

        foreach ($this->db->setQuery($q)->loadObjectList() as $w) {
            if (\in_array((int) $w->id, $keep, true)) {
                continue;
            }

            $sql = $w->n > 0
                ? $this->db->createQuery()->update('#__protis_workpackages')->set('published = 0')->where('id = ' . (int) $w->id)
                : $this->db->createQuery()->delete('#__protis_workpackages')->where('id = ' . (int) $w->id);
            $this->db->setQuery($sql)->execute();
        }
    }

    /** Loghi singoli del progetto, nell'ordine di stampa. @return string[] */
    public static function logoList(object $project): array
    {
        $list = json_decode((string) ($project->logos ?? ''), true);

        if (\is_array($list)) {
            return array_values(array_filter($list, 'is_string'));
        }

        // progetti precedenti: un solo logo (la fascia già pronta)
        return $project->logo ?? '' ? [$project->logo] : [];
    }

    /**
     * @param  array  $logos    [['file' => nome, 'remove' => 0|1], ...] nell'ordine scelto
     * @param  array  $uploads  file caricati ($_FILES normalizzato) da aggiungere in coda
     */
    private function saveLogos(int $projectId, array $logos, array $uploads): void
    {
        $project = $this->db->setQuery('SELECT logo, logos FROM #__protis_projects WHERE id = ' . $projectId)->loadObject();
        $current = self::logoList($project);
        $dir     = self::logoDir();

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $list    = [];
        $removed = [];

        foreach ($logos as $l) {
            $file = basename((string) ($l['file'] ?? ''));

            if ($file === '' || !\in_array($file, $current, true)) {
                continue;
            }

            if (!empty($l['remove'])) {
                $removed[] = $file;
            } else {
                $list[] = $file;
            }
        }

        // loghi esistenti non presenti nel form (form vecchio) restano
        foreach ($current as $f) {
            if (!\in_array($f, $list, true) && !\in_array($f, $removed, true)) {
                $list[] = $f;
            }
        }

        $n = 0;

        foreach ($uploads as $file) {
            if (!\is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

            if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
                throw new \InvalidArgumentException('Logo troppo grande: ' . $file['name'] . ' (massimo 10 MB).');
            }

            if (!\in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) || !@getimagesize($file['tmp_name'])) {
                throw new \InvalidArgumentException('Logo non valido: ' . $file['name'] . ' (formati ammessi PNG, JPG, GIF, WEBP).');
            }

            $name = \sprintf('progetto_%d_l%s%02d.%s', $projectId, date('YmdHis'), ++$n, $ext);

            if (!File::upload($file['tmp_name'], $dir . '/' . $name)) {
                throw new \RuntimeException('Impossibile salvare il logo ' . $file['name']);
            }

            // immagini molto grandi: ridotte (la fascia usa comunque un'altezza uniforme)
            $list[] = LogoBanner::shrink($dir . '/' . $name);
        }

        if ($list === $current && !$removed && $project->logos !== null) {
            return;
        }

        // nuova fascia composta
        $banner = '';

        if (\count($list) === 1) {
            // un solo file (es. fascia già pronta della V.2): si usa così com'è
            $banner = $list[0];
        } elseif ($list) {
            $banner = \sprintf('progetto_%d_fascia_%s_%s.png', $projectId, date('YmdHis'), bin2hex(random_bytes(3)));
            LogoBanner::compose(array_map(static fn ($f) => $dir . '/' . $f, $list), $dir . '/' . $banner);
        }

        // pulizia: vecchia fascia e loghi rimossi (solo file generati da PROTIS IBF per questo progetto)
        foreach (array_merge($removed, [$project->logo]) as $old) {
            if ($old && $old !== $banner && !\in_array($old, $list, true) && preg_match('/^progetto_' . $projectId . '_/', $old) && is_file($dir . '/' . $old)) {
                @unlink($dir . '/' . $old);
            }
        }

        $this->db->setQuery(
            $this->db->createQuery()->update('#__protis_projects')
                ->set('logo = ' . $this->db->quote($banner))
                ->set('logos = ' . $this->db->quote(json_encode($list)))
                ->where('id = ' . $projectId)
        )->execute();
    }

    public function delete(int $id): void
    {
        $q = $this->db->createQuery()->select('COUNT(*)')->from('#__protis_hours AS h')
            ->join('INNER', '#__protis_workpackages AS w', 'w.id = h.wp_id')
            ->where('w.project_id = ' . $id);

        if ((int) $this->db->setQuery($q)->loadResult() > 0) {
            // con ore registrate si archivia soltanto
            $this->db->setQuery($this->db->createQuery()->update('#__protis_projects')->set('published = 0')->where('id = ' . $id))->execute();
            $this->log('project.archive', 'Progetto ' . $id . ' archiviato (ore presenti)');

            return;
        }

        $this->db->setQuery($this->db->createQuery()->delete('#__protis_projects')->where('id = ' . $id))->execute();
        $this->log('project.delete', 'Progetto ' . $id . ' eliminato');
    }

    /**
     * Progetti (con WP attivi) assegnati a una persona.
     *
     * @return object[]
     */
    public function forPerson(int $personId, bool $onlyPublished = true): array
    {
        $q = $this->db->createQuery()
            ->select("p.id, p.acronimo, COALESCE(NULLIF(p.cup, ''), p.grant_agreement) AS cup, p.titolo, p.layout, p.data_inizio, p.data_fine, p.data_proroga, p.published")
            ->select('a.id AS assignment_id, a.monteore_min, a.monte_annuo_min, a.layout AS assignment_layout, a.nota')
            ->from('#__protis_assignments AS a')
            ->join('INNER', '#__protis_projects AS p', 'p.id = a.project_id')
            ->where('a.person_id = :pid')->bind(':pid', $personId, ParameterType::INTEGER)
            ->order('p.acronimo');

        if ($onlyPublished) {
            $q->where('p.published = 1');
        }

        $projects = $this->db->setQuery($q)->loadObjectList();

        foreach ($projects as $p) {
            $p->wps = array_values(array_map(
                static fn ($w) => (object) ['id' => (int) $w->id, 'label' => $w->label, 'code' => $w->code],
                $this->workpackages((int) $p->id, true)
            ));
        }

        return $projects;
    }

    /* ---------------- Associazioni persona ↔ progetto ---------------- */

    /** @return object[] */
    public function assignments(?int $projectId = null, ?int $personId = null, ?array $personIds = null): array
    {
        $q = $this->db->createQuery()
            ->select("a.*, p.acronimo, p.titolo AS project_title, p.published AS project_published, COALESCE(NULLIF(p.cup, ''), p.grant_agreement) AS cup, p.layout AS project_layout, pe.nome, pe.cognome, pe.uid, pe.matricola, pe.site_id, pe.external, pe.avatar")
            ->from('#__protis_assignments AS a')
            ->join('INNER', '#__protis_projects AS p', 'p.id = a.project_id')
            ->join('INNER', '#__protis_people AS pe', 'pe.id = a.person_id')
            ->order('p.acronimo, pe.cognome, pe.nome');

        if ($projectId) {
            $q->where('a.project_id = ' . (int) $projectId);
        }

        if ($personId) {
            $q->where('a.person_id = ' . (int) $personId);
        }

        if ($personIds !== null) {
            $q->where($personIds ? 'a.person_id IN (' . implode(',', array_map('intval', $personIds)) . ')' : '0');
        }

        return $this->db->setQuery($q)->loadObjectList();
    }

    public function setPublished(int $id, bool $on): void
    {
        $this->db->setQuery($this->db->createQuery()->update('#__protis_projects')->set('published = ' . ($on ? 1 : 0))->set('modified = ' . $this->db->quote($this->now()))->where('id = ' . $id))->execute();
        $this->log('project.' . ($on ? 'activate' : 'archive'), 'Progetto ' . $id . ($on ? ' attivato' : ' disattivato'));
    }

    public function assignmentExists(int $personId, int $projectId): bool
    {
        $q = $this->db->createQuery()->select('COUNT(*)')->from('#__protis_assignments')
            ->where('person_id = ' . $personId)->where('project_id = ' . $projectId);

        return (int) $this->db->setQuery($q)->loadResult() > 0;
    }

    /**
     * Associa una persona al progetto (nuova associazione).
     * $monteore = ore previste sul progetto da rendicontare, $annuo = monte ore annuo (facoltativi, in ore).
     */
    public function saveAssignment(int $personId, int $projectId, ?string $monteore, ?string $annuo = null): void
    {
        $toMin = static fn (?string $v) => $v !== null && trim($v) !== '' ? Time::hoursStringToMinutes($v) : null;

        $this->upsert('#__protis_assignments', [
            'person_id'       => $personId,
            'project_id'      => $projectId,
            'monteore_min'    => $toMin($monteore),
            'monte_annuo_min' => $toMin($annuo),
            'layout'          => '',
            'nota'            => '',
            'created'         => $this->now(),
        ], ['monteore_min', 'monte_annuo_min']);
        $this->log('assignment.save', "Associazione persona $personId → progetto $projectId", $personId);
    }

    /** Modifica di un solo campo ore di un'associazione esistente (monteore | monte_annuo). */
    public function updateAssignmentHours(int $personId, int $projectId, string $field, ?string $value): void
    {
        $col = ['monteore' => 'monteore_min', 'monte_annuo' => 'monte_annuo_min'][$field] ?? throw new \InvalidArgumentException('Campo non valido');
        $min = $value !== null && trim($value) !== '' ? Time::hoursStringToMinutes($value) : null;

        $this->db->setQuery(
            $this->db->createQuery()->update('#__protis_assignments')
                ->set($this->db->quoteName($col) . ' = ' . ($min === null ? 'NULL' : (int) $min))
                ->where('person_id = ' . $personId)->where('project_id = ' . $projectId)
        )->execute();
        $this->log('assignment.save', "Associazione persona $personId → progetto $projectId: $col aggiornato", $personId);
    }

    public function getAssignment(int $id): ?object
    {
        return $this->db->setQuery($this->db->createQuery()->select('*')->from('#__protis_assignments')->where('id = ' . $id))->loadObject() ?: null;
    }

    /** Minuti rendicontati da una persona sui WP di un progetto. */
    public function reportedOnProject(int $personId, int $projectId): int
    {
        $q = $this->db->createQuery()->select('COALESCE(SUM(h.minutes), 0)')->from('#__protis_hours AS h')
            ->join('INNER', '#__protis_workpackages AS w', 'w.id = h.wp_id')
            ->where('h.person_id = ' . $personId)->where('w.project_id = ' . $projectId);

        return (int) $this->db->setQuery($q)->loadResult();
    }

    public function deleteAssignment(int $id): void
    {
        $this->db->setQuery($this->db->createQuery()->delete('#__protis_assignments')->where('id = ' . $id))->execute();
        $this->log('assignment.delete', 'Associazione ' . $id . ' rimossa');
    }

    private function dateOrNull(?string $d): ?string
    {
        $d = trim((string) $d);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $d !== '0000-00-00' ? $d : null;
    }
}
