<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Acl;
use Joomla\CMS\User\User;
use Joomla\Database\ParameterType;

final class PeopleService extends AbstractService
{
    public function get(int $id): ?object
    {
        $q = $this->db->createQuery()
            ->select('p.*, s.sede AS sede_nome, s.codice_sede')
            ->from('#__protis_people AS p')
            ->join('LEFT', '#__protis_sites AS s', 's.id = p.site_id')
            ->where('p.id = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return $this->db->setQuery($q)->loadObject() ?: null;
    }

    public function getByUid(string $uid): ?object
    {
        $q = $this->db->createQuery()
            ->select('id')
            ->from('#__protis_people')
            ->where('LOWER(uid) = LOWER(:uid)')
            ->bind(':uid', $uid);
        $id = (int) $this->db->setQuery($q)->loadResult();

        return $id ? $this->get($id) : null;
    }

    /** Persona collegata all'utente Joomla (creata al volo per utenti locali senza LDAP). */
    public function forUser(User $user, bool $create = true): ?object
    {
        if ($user->guest) {
            return null;
        }

        $q = $this->db->createQuery()
            ->select('id')
            ->from('#__protis_people')
            ->where('(user_id = :uidn OR LOWER(uid) = LOWER(:uname))')
            ->bind(':uidn', $user->id, ParameterType::INTEGER)
            ->bind(':uname', $user->username)
            ->order('user_id DESC');
        $id = (int) $this->db->setQuery($q, 0, 1)->loadResult();

        if ($id) {
            $person = $this->get($id);

            if ((int) $person->user_id !== (int) $user->id) {
                $this->db->setQuery(
                    $this->db->createQuery()->update('#__protis_people')->set('user_id = ' . (int) $user->id)->where('id = ' . $id)
                )->execute();
                $person->user_id = $user->id;
            }

            return $person;
        }

        if (!$create) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($user->name), 2);
        $row   = (object) [
            'user_id' => (int) $user->id,
            'uid'     => $user->username,
            'nome'    => mb_strtoupper($parts[0] ?? ''),
            'cognome' => mb_strtoupper($parts[1] ?? ''),
            'email'   => $user->email,
            'source'  => 'manual',
            'active'  => 1,
            'created' => $this->now(),
        ];
        $this->db->insertObject('#__protis_people', $row, 'id');

        return $this->get((int) $row->id);
    }

    /** Sincronizza la persona con gli attributi LDAP CNR (chiamato dal plugin di autenticazione). */
    public function upsertFromLdap(array $a): int
    {
        $uid = trim((string) ($a['uid'] ?? ''));

        if ($uid === '') {
            return 0;
        }

        $siteId = null;

        if (!empty($a['departmentnumber'])) {
            $q = $this->db->createQuery()->select('id')->from('#__protis_sites')
                ->where('epas_only = 0')->where('codice_sede = :c')->bind(':c', $a['departmentnumber']);
            $siteId = (int) $this->db->setQuery($q)->loadResult() ?: null;
        }

        $existing = $this->getByUid($uid);
        $data     = [
            'uid'            => $uid,
            'matricola'      => ($a['matricola'] ?? '') !== '' ? $a['matricola'] : null,
            'nome'           => mb_strtoupper((string) ($a['cnrnome'] ?? '')),
            'cognome'        => mb_strtoupper((string) ($a['cnrcognome'] ?? '')),
            'email'          => (string) ($a['mail'] ?? ''),
            'tipo_contratto' => (string) ($a['cnrextra4'] ?? ''),
            'ldap_gruppo'    => (string) ($a['cnrgruppo3'] ?? ''),
            'ldap_sede'      => (string) ($a['departmentnumber'] ?? ''),
            'site_id'        => $siteId,
            'source'         => 'ldap',
            'modified'       => $this->now(),
        ];

        // collega di altro istituto: resta "esterno" (niente sede dell'istituto, presenze manuali)
        if ($existing && !empty($existing->external)) {
            $data['source']  = 'external';
            $data['site_id'] = $existing->site_id;
        }

        if (!empty($a['codicefiscale'])) {
            $data['codice_fiscale'] = mb_strtoupper($a['codicefiscale']);
        }

        if ($existing) {
            // non sovrascrivere dati compilati a mano se LDAP li restituisce vuoti
            foreach (['tipo_contratto', 'nome', 'cognome', 'email', 'ldap_gruppo'] as $k) {
                if ($data[$k] === '') {
                    unset($data[$k]);
                }
            }

            // codice fiscale, contratto e sede si correggono nella scheda: LDAP li compila solo se mancanti
            foreach (['codice_fiscale', 'tipo_contratto', 'site_id'] as $k) {
                if (!empty($existing->$k)) {
                    unset($data[$k]);
                }
            }

            if ($data['matricola'] === null) {
                unset($data['matricola']);
            }

            $obj = (object) (['id' => $existing->id] + $data);
            $this->db->updateObject('#__protis_people', $obj, 'id', true);

            return (int) $existing->id;
        }

        $obj          = (object) $data;
        $obj->created = $this->now();
        $obj->active  = 1;
        $this->db->insertObject('#__protis_people', $obj, 'id');

        return (int) $obj->id;
    }

    /**
     * Elenco persone visibili all'utente corrente.
     *
     * @return object[]
     */
    public function listVisible(?object $me, string $search = '', bool $onlyActive = false): array
    {
        $q = $this->db->createQuery()
            ->select('p.*, s.sede AS sede_nome')
            ->select('(SELECT COUNT(*) FROM #__protis_assignments a WHERE a.person_id = p.id) AS n_progetti')
            ->from('#__protis_people AS p')
            ->join('LEFT', '#__protis_sites AS s', 's.id = p.site_id')
            ->order('p.cognome, p.nome');

        if (!Acl::canManageAllSites()) {
            if (Acl::canManage() && $me && $me->site_id) {
                $q->where('(p.site_id = ' . (int) $me->site_id . ' OR p.site_id IS NULL)');
            } else {
                $q->where('p.id = ' . (int) ($me->id ?? 0));
            }
        }

        if ($onlyActive) {
            $q->where('p.active = 1');
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where('(p.nome LIKE :s1 OR p.cognome LIKE :s2 OR p.uid LIKE :s3 OR p.matricola LIKE :s4)')
                ->bind(':s1', $like)->bind(':s2', $like)->bind(':s3', $like)->bind(':s4', $like);
        }

        return $this->db->setQuery($q)->loadObjectList();
    }

    /** L'utente corrente può vedere/modificare il calendario di questa persona? */
    public function canAccess(?object $me, object $person): bool
    {
        if ($me && (int) $me->id === (int) $person->id) {
            return true;
        }

        if (Acl::canManageAllSites()) {
            return true;
        }

        return Acl::canManage() && $me && $me->site_id && ((int) $person->site_id === (int) $me->site_id || !$person->site_id);
    }

    public function saveProfile(int $id, array $data, bool $asManager): void
    {
        $allowed = ['codice_fiscale', 'tipo_contratto', 'site_id', 'data_assunzione', 'sede_esterna'];

        $current = $this->get($id);

        // i colleghi di altri istituti indicano istituto e sede di appartenenza
        if (!empty($current->external)) {
            $allowed = array_merge($allowed, ['ldap_gruppo', 'sede_esterna']);
        }

        if ($asManager) {
            $allowed = array_merge($allowed, ['active', 'external', 'ldap_gruppo', 'sede_esterna', 'epas_off', 'epas_site_id']);

            // nominativo, email e matricola si modificano solo per le persone inserite a mano (non LDAP)

            if ($current && $current->source === 'manual') {
                $allowed = array_merge($allowed, ['nome', 'cognome', 'email', 'matricola']);
            }
        }

        $obj = (object) ['id' => $id, 'modified' => $this->now()];

        foreach ($allowed as $k) {
            if (!\array_key_exists($k, $data)) {
                continue;
            }

            $v = \is_string($data[$k]) ? trim($data[$k]) : $data[$k];

            $obj->$k = match ($k) {
                'codice_fiscale', 'nome', 'cognome' => mb_strtoupper((string) $v),
                'site_id'   => ctype_digit((string) $v) && (int) $v > 0 ? (int) $v : null, // "altro" = sede scritta a mano
                'data_assunzione' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : null,
                'matricola' => $v !== '' ? (string) $v : null,
                'active', 'external', 'epas_off' => (int) (bool) $v,
                'epas_site_id' => ctype_digit((string) $v) && (int) $v > 0 ? (int) $v : null,
                default     => (string) $v,
            };
        }

        // altro istituto: niente sede dell'istituto; sede dell'istituto: niente sede esterna
        $ext = isset($obj->external) ? (int) $obj->external : (int) ($current->external ?? 0);

        if ($ext) {
            $obj->site_id = null;
        } elseif (!empty($obj->site_id)) {
            $obj->sede_esterna = ''; // sede dell'istituto scelta dal menu
        } elseif (\array_key_exists('site_id', $data) && ($data['site_id'] ?? '') !== 'altro') {
            $obj->sede_esterna = '';
        }

        $this->db->updateObject('#__protis_people', $obj, 'id', true);
        $this->log('profile.save', 'Profilo aggiornato', $id);
    }

    /** Crea una persona senza account LDAP (collaboratore esterno, inserimento manuale ore). */
    public function createManual(array $data): int
    {
        $obj = (object) [
            'uid'            => trim((string) $data['uid']),
            'nome'           => mb_strtoupper(trim((string) ($data['nome'] ?? ''))),
            'cognome'        => mb_strtoupper(trim((string) ($data['cognome'] ?? ''))),
            'email'          => trim((string) ($data['email'] ?? '')),
            'codice_fiscale' => mb_strtoupper(trim((string) ($data['codice_fiscale'] ?? ''))),
            'tipo_contratto' => trim((string) ($data['tipo_contratto'] ?? '')),
            'site_id'        => !empty($data['site_id']) ? (int) $data['site_id'] : null,
            'matricola'      => !empty($data['matricola']) ? trim((string) $data['matricola']) : null,
            'source'         => 'manual',
            'active'         => 1,
            'created'        => $this->now(),
        ];

        if ($obj->uid === '') {
            throw new \InvalidArgumentException('Username obbligatorio');
        }

        if ($this->getByUid($obj->uid)) {
            throw new \InvalidArgumentException('Esiste già una persona con username ' . $obj->uid);
        }

        $this->db->insertObject('#__protis_people', $obj, 'id');

        return (int) $obj->id;
    }

    /** @return object[] */
    public function sites(bool $withSecrets = false): array
    {
        $cols = $withSecrets ? '*' : 'id, ente, istituto, sigla, sede, codice_sede, published, epas_only, (epas_user IS NOT NULL AND epas_user <> \'\') AS has_epas';
        $q    = $this->db->createQuery()->select($cols)->from('#__protis_sites')->order('epas_only, sede');

        return $this->db->setQuery($q)->loadObjectList();
    }

    /** Sedi dell'istituto (esclude le sedi di altri istituti usate solo per ePAS). @return object[] */
    public function instituteSites(): array
    {
        return array_values(array_filter($this->sites(), static fn ($s) => empty($s->epas_only)));
    }

    /** Sedi di altri istituti con credenziali ePAS, per i colleghi esterni. @return object[] */
    public function externalEpasSites(): array
    {
        return array_values(array_filter($this->sites(), static fn ($s) => !empty($s->epas_only)));
    }

    /** @return string[] */
    public function contractTypes(): array
    {
        $q = $this->db->createQuery()->select('label')->from('#__protis_contract_types')->order('ordering, label');

        return $this->db->setQuery($q)->loadColumn();
    }

    /**
     * Le presenze arrivano da ePAS? Serve la matricola e l'interruttore "Presenze da ePAS" acceso;
     * per i colleghi di altri istituti serve anche la sede ePAS del loro istituto (con le sue credenziali).
     */
    public static function usesEpas(object $person): bool
    {
        if (empty($person->matricola) || !empty($person->epas_off)) {
            return false;
        }

        return empty($person->external) || !empty($person->epas_site_id);
    }

    /** Perché una persona non usa ePAS (testo breve per la scheda), null se lo usa. */
    public static function epasReason(object $person): ?string
    {
        return match (true) {
            empty($person->matricola)    => 'senza matricola CNR: presenze sempre inserite a mano',
            !empty($person->epas_off)    => 'inserimento manuale delle presenze nel calendario',
            !empty($person->external) && empty($person->epas_site_id) => 'altro istituto: scegli la sede ePAS del suo istituto per leggere le presenze',
            default => null,
        };
    }

    /**
     * Abilita colleghi di altri istituti tramite UID LDAP: possono accedere anche se la loro sede
     * non è dell'istituto e inseriscono le presenze a mano. Ritorna [uid => person_id].
     *
     * @param  string[]  $uids
     */
    public function enableExternal(array $uids): array
    {
        $out = [];

        foreach ($uids as $uid) {
            $uid = strtolower(trim((string) $uid));

            if ($uid === '' || !preg_match('/^[a-z0-9._\-@]+$/', $uid)) {
                continue;
            }

            $p = $this->getByUid($uid);

            if ($p) {
                // persona già nota: se non è di una sede dell'istituto la si marca come esterna e attiva
                if (empty($p->site_id) || !empty($p->external)) {
                    $obj = (object) ['id' => $p->id, 'external' => 1, 'active' => 1, 'modified' => $this->now()];
                    $this->db->updateObject('#__protis_people', $obj, 'id');
                }

                $out[$uid] = (int) $p->id;
                continue;
            }

            $obj = (object) [
                'uid'      => $uid,
                'source'   => 'external',
                'external' => 1,
                'active'   => 1,
                'created'  => $this->now(),
            ];

            // nome e istituto da LDAP, se il plugin è configurato
            $info = LdapDirectory::lookup($uid);

            if ($info) {
                $obj->nome           = mb_strtoupper($info['cnrnome']);
                $obj->cognome        = mb_strtoupper($info['cnrcognome']);
                $obj->email          = $info['mail'];
                $obj->matricola      = $info['matricola'] !== '' ? $info['matricola'] : null;
                $obj->tipo_contratto = $info['cnrextra4'];
                $obj->ldap_gruppo    = $info['cnrgruppo3'];
                $obj->ldap_sede      = $info['departmentnumber'];
            }

            $this->db->insertObject('#__protis_people', $obj, 'id');
            $out[$uid] = (int) $obj->id;
            $this->log('person.external', 'Abilitato collega esterno ' . $uid, (int) $obj->id);
        }

        return $out;
    }

    /** Collega esterno abilitato e attivo? (usato dal plugin di accesso) */
    public function isEnabledExternal(string $uid): bool
    {
        $q = $this->db->createQuery()->select('COUNT(*)')->from('#__protis_people')
            ->where('LOWER(uid) = LOWER(:u)')->where('external = 1')->where('active = 1')->bind(':u', $uid);

        return (int) $this->db->setQuery($q)->loadResult() > 0;
    }

    /** Profilo incompleto (necessario per l'intestazione del timesheet)? */
    public static function missingFields(object $person): array
    {
        $missing = [];

        if (trim((string) $person->codice_fiscale) === '') {
            $missing[] = 'Codice fiscale';
        }

        if (trim((string) $person->tipo_contratto) === '') {
            $missing[] = 'Tipo di contratto';
        }

        if (empty($person->site_id) && empty($person->external) && trim((string) ($person->sede_esterna ?? '')) === '') {
            $missing[] = 'Sede di afferenza';
        }

        return $missing;
    }

    /* ---------------- Foto del profilo ---------------- */

    public const AVATAR_MAX_BYTES = 10 * 1024 * 1024;
    private const AVATAR_SIZE     = 320;

    public static function avatarDir(): string
    {
        return JPATH_ROOT . '/images/protis/avatar';
    }

    public static function avatarUrl(?object $person): string
    {
        $f = (string) ($person->avatar ?? '');

        return $f !== '' && is_file(self::avatarDir() . '/' . $f)
            ? \Joomla\CMS\Uri\Uri::root(true) . '/images/protis/avatar/' . rawurlencode($f)
            : '';
    }

    /**
     * Salva la foto: massimo 10 MB, ritagliata al centro in quadrato e ridotta a 320×320 (JPEG).
     *
     * @param array $file elemento di $_FILES
     */
    public function saveAvatar(int $personId, array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new \InvalidArgumentException('Scegli una foto da caricare.');
        }

        if (($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE || ($file['size'] ?? 0) > self::AVATAR_MAX_BYTES) {
            throw new \InvalidArgumentException('La foto supera i 10 MB.');
        }

        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Caricamento della foto non riuscito.');
        }

        $info = @getimagesize($file['tmp_name']);
        $src  = match ($info[2] ?? 0) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
            IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
            IMAGETYPE_GIF  => @imagecreatefromgif($file['tmp_name']),
            IMAGETYPE_WEBP => \function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
            default        => false,
        };

        if (!$src) {
            throw new \InvalidArgumentException('Formato non valido: usa una foto JPG, PNG, GIF o WEBP.');
        }

        // orientamento delle foto da smartphone (EXIF)
        if (($info[2] ?? 0) === IMAGETYPE_JPEG && \function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data($file['tmp_name'])['Orientation'] ?? 1);
            $src = match ($o) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };
        }

        $w    = imagesx($src);
        $h    = imagesy($src);
        $side = min($w, $h);
        $out  = imagecreatetruecolor(self::AVATAR_SIZE, self::AVATAR_SIZE);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 3), self::AVATAR_SIZE, self::AVATAR_SIZE, $side, $side);
        imagedestroy($src);

        $dir = self::avatarDir();

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = \sprintf('p%d_%s.jpg', $personId, bin2hex(random_bytes(6)));

        if (!imagejpeg($out, $dir . '/' . $name, 85)) {
            throw new \RuntimeException('Impossibile salvare la foto.');
        }

        imagedestroy($out);
        $this->removeAvatar($personId, false);
        $row = (object) ['id' => $personId, 'avatar' => $name, 'modified' => $this->now()];
        $this->db->updateObject('#__protis_people', $row, 'id');
        $this->log('profile.avatar', 'Foto del profilo aggiornata', $personId);
    }

    public function removeAvatar(int $personId, bool $save = true): void
    {
        $p = $this->get($personId);

        if ($p && $p->avatar !== '' && is_file(self::avatarDir() . '/' . basename($p->avatar))) {
            @unlink(self::avatarDir() . '/' . basename($p->avatar));
        }

        if ($save) {
            $row = (object) ['id' => $personId, 'avatar' => '', 'modified' => $this->now()];
            $this->db->updateObject('#__protis_people', $row, 'id');
            $this->log('profile.avatar', 'Foto del profilo rimossa', $personId);
        }
    }
}
