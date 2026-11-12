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
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseFactory;
use Joomla\Database\DatabaseInterface;

/**
 * Migrazione dati da PROTIS IBF V.2 (WordPress + database dinamico) allo schema normalizzato V.3.
 *
 * Sorgenti:
 *  - DB WordPress (prefisso indicato con --wp-prefix): utenti, codici assenza, setting ePAS, ruoli WP
 *  - DB progetti e calendari della V.2: elencoprogetti, Progetto_*, utenti_progetti, calendario_*
 *  - (opzionale) cartella V.2 per loghi progetto e JSON ePAS
 */
final class LegacyImporter extends AbstractService
{
    /** @var callable */
    private $out;

    /** @var string[] acronimi dei progetti con intestazione SAL (opzione --sal) */
    public array $salProjects = [];

    private array $warnings = [];
    private array $stats    = [];

    /** @var array<string,int> uid(lower) => person_id */
    private array $personByUid = [];

    /** @var array<string,int> matricola => person_id */
    private array $personByMatricola = [];

    /** @var array<string,int[]> cup normalizzato => project_ids */
    private array $projectsByCup = [];

    /** @var array<int,array<string,int>> project_id => [wp_N => wp_id] */
    private array $wpByCode = [];

    /** @var array<int,int[]> person_id => project_ids */
    private array $assignedProjects = [];

    private array $absenceCodes = [];

    public function __construct(
        private DatabaseInterface $wp,
        private DatabaseInterface $proj,
        private string $wpPrefix,
        private ?string $legacyPath = null,
        private ?string $legacyKey = null,
        ?callable $output = null,
    ) {
        parent::__construct();
        $this->out = $output ?? static function (string $m): void {
        };
    }

    public static function connect(array $o): DatabaseInterface
    {
        return (new DatabaseFactory())->getDriver('mysqli', [
            'host'     => $o['host'] ?? 'localhost',
            'user'     => $o['user'] ?? 'root',
            'password' => $o['password'] ?? '',
            'database' => $o['database'],
            'prefix'   => '',
        ]);
    }

    /**
     * Legge la chiave sodium della V.2 senza eseguire codice.
     * Accetta la cartella .env, il file chiave.env (SODIUM_KEY=base64) o configKEY.php.
     */
    public static function readLegacyKey(string $file): ?string
    {
        if (is_dir($file)) {
            $file = rtrim($file, '/') . '/chiave.env';
        } elseif (basename($file) === 'configKEY.php' && is_file(\dirname($file) . '/chiave.env')) {
            $file = \dirname($file) . '/chiave.env';
        }

        if (!is_file($file)) {
            return null;
        }

        $ini = @parse_ini_file($file, false, INI_SCANNER_RAW);

        if (\is_array($ini) && !empty($ini['SODIUM_KEY'])) {
            $key = base64_decode(trim($ini['SODIUM_KEY']), true);

            return $key !== false && \strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ? $key : null;
        }

        $src = (string) file_get_contents($file);

        if (preg_match("/SODIUM_SECRET_KEY['\"]\s*,\s*(.+?)\)\s*;/s", $src, $m)) {
            $expr = trim($m[1]);

            if (preg_match("/^(?:base64_decode|sodium_base642bin)\(\s*['\"]([^'\"]+)['\"]/", $expr, $b)) {
                return base64_decode($b[1], true) ?: null;
            }

            if (preg_match("/^hex2bin\(\s*['\"]([0-9a-fA-F]+)['\"]/", $expr, $h)) {
                return hex2bin($h[1]) ?: null;
            }

            if (preg_match("/^['\"](.*)['\"]$/s", $expr, $s)) {
                return stripcslashes($s[1]);
            }
        }

        return null;
    }

    private function say(string $msg): void
    {
        ($this->out)($msg);
    }

    private function warn(string $msg): void
    {
        $this->warnings[] = $msg;
        ($this->out)('  ⚠ ' . $msg);
    }

    private function count(string $k, int $n = 1): void
    {
        $this->stats[$k] = ($this->stats[$k] ?? 0) + $n;
    }

    public function run(bool $createJoomlaUsers = true, string $authorScope = 'all'): array
    {
        $this->say('1/7 Codici di assenza');
        $this->importAbsenceCodes();

        $this->say('2/7 Tipi di contratto');
        $this->importContractTypes();

        $this->say('3/7 Sedi e credenziali ePAS');
        $this->importSites();

        $this->say('4/7 Persone' . ($createJoomlaUsers ? ' e account Joomla' : ''));
        $this->importPeople($createJoomlaUsers, $authorScope);

        $this->say('5/7 Progetti e work package');
        $this->importProjects();

        $this->say('6/7 Associazioni persona ↔ progetto');
        $this->importAssignments();

        $this->say('7/7 Calendari e ore rendicontate');
        $this->importCalendars();

        return ['stats' => $this->stats, 'warnings' => $this->warnings];
    }

    private function wpTable(string $name): string
    {
        return $this->wp->quoteName($this->wpPrefix . $name);
    }

    private function importAbsenceCodes(): void
    {
        $rows = $this->wp->setQuery('SELECT * FROM ' . $this->wpTable('codici_assenza'))->loadObjectList();

        foreach ($rows as $r) {
            $code     = strtoupper(trim($r->codice));
            $category = \in_array($r->categoria, array_keys(CalendarService::CATEGORIES), true) ? $r->categoria : 'altro';
            $minutes  = $r->orario !== null ? Time::toMinutes($r->orario) : null;
            $blocks   = \in_array($category, ['malattia', 'ferie', 'permessi', 'altro'], true) || \in_array($code, ['91', '661G'], true);

            $this->upsert('#__protis_absence_codes', [
                'code'        => $code,
                'description' => $r->descrizione,
                'category'    => $category,
                'minutes'     => $minutes,
                'blocks_fill' => $blocks ? 1 : 0,
                'modified'    => $this->now(),
            ], ['description', 'category', 'minutes', 'blocks_fill', 'modified']);
            $this->count('codici_assenza');
        }

        foreach ($this->db->setQuery('SELECT * FROM #__protis_absence_codes')->loadObjectList() as $c) {
            $this->absenceCodes[strtoupper($c->code)] = $c;
        }
    }

    private function importContractTypes(): void
    {
        try {
            $rows = $this->proj->setQuery('SELECT ente, tipocontratto FROM tipo_contratto_ente')->loadObjectList();
        } catch (\Throwable) {
            return;
        }

        $existing = array_map('mb_strtolower', $this->db->setQuery('SELECT label FROM #__protis_contract_types')->loadColumn());

        foreach ($rows as $r) {
            if (!\in_array(mb_strtolower($r->tipocontratto), $existing, true)) {
                $ct = (object) ['ente' => $r->ente, 'label' => $r->tipocontratto, 'ordering' => 100];
                $this->db->insertObject('#__protis_contract_types', $ct);
                $this->count('tipi_contratto');
            }
        }
    }

    private function importSites(): void
    {
        $rows = $this->wp->setQuery('SELECT * FROM ' . $this->wpTable('setting_epas'))->loadObjectList();

        foreach ($rows as $r) {
            $user = $pass = null;

            if ($this->legacyKey) {
                $u = Crypto::decrypt($r->user, $this->legacyKey);
                $p = Crypto::decrypt($r->password, $this->legacyKey);

                if ($u !== null && $p !== null) {
                    $user = Crypto::encrypt($u);
                    $pass = Crypto::encrypt($p);
                } else {
                    $this->warn("Credenziali ePAS sede {$r->sede}: decifratura fallita (chiave V.2 errata?)");
                }
            }

            $data = [
                'ente'        => $r->ente,
                'istituto'    => $r->istituto,
                'sigla'       => $r->siglaistituto,
                'sede'        => $r->sede,
                'codice_sede' => $r->codice_istituto,
                'published'   => 1,
                'modified'    => $this->now(),
            ];
            $upd = ['ente', 'istituto', 'sigla', 'sede', 'modified'];

            if ($user !== null) {
                $data['epas_user']     = $user;
                $data['epas_password'] = $pass;
                $upd                   = array_merge($upd, ['epas_user', 'epas_password']);
            }

            $this->upsert('#__protis_sites', $data, $upd);
            $this->count('sedi');
        }

        if (!$this->legacyKey) {
            $this->warn('Chiave V.2 (.env/configKEY.php) non fornita: reinserire le credenziali ePAS in Impostazioni → Sedi.');
        }
    }

    private function importPeople(bool $createUsers, string $authorScope): void
    {
        $sites = $this->db->setQuery('SELECT codice_sede, id FROM #__protis_sites')->loadAssocList('codice_sede', 'id');

        // ruoli WordPress
        $roles = [];

        try {
            $q = 'SELECT u.user_login, m.meta_value FROM ' . $this->wpTable('users') . ' u JOIN ' . $this->wpTable('usermeta')
                . ' m ON m.user_id = u.ID AND m.meta_key = ' . $this->wp->quote($this->wpPrefix . 'capabilities');

            foreach ($this->wp->setQuery($q)->loadObjectList() as $r) {
                $caps                                    = @unserialize($r->meta_value, ['allowed_classes' => false]) ?: [];
                $roles[mb_strtolower($r->user_login)] = array_keys(array_filter((array) $caps));
            }
        } catch (\Throwable $e) {
            $this->warn('Ruoli WordPress non letti: ' . $e->getMessage());
        }

        $groups = $this->db->setQuery("SELECT title, id FROM #__usergroups WHERE title LIKE 'PROTIS IBF%'")->loadAssocList('title', 'id');
        $registered = (int) $this->db->setQuery("SELECT id FROM #__usergroups WHERE title = 'Registered'")->loadResult() ?: 2;

        $rows = $this->wp->setQuery('SELECT * FROM ' . $this->wpTable('utenti') . ' ORDER BY id')->loadObjectList();

        foreach ($rows as $r) {
            $uid = trim((string) $r->username);

            if ($uid === '') {
                continue;
            }

            $data = [
                'uid'            => $uid,
                'matricola'      => trim((string) $r->matricola) !== '' ? trim((string) $r->matricola) : null,
                'nome'           => mb_strtoupper(trim((string) $r->nome)),
                'cognome'        => mb_strtoupper(trim((string) $r->cognome)),
                'email'          => (string) $r->email,
                'codice_fiscale' => mb_strtoupper(trim((string) $r->codice_fiscale)),
                'tipo_contratto' => (string) $r->tipo_contratto,
                'site_id'        => $sites[(string) $r->codice_sede] ?? null,
                'ldap_sede'      => (string) $r->codice_sede,
                'ldap_gruppo'    => (string) $r->istituto_afferenza,
                'source'         => trim((string) $r->matricola) !== '' ? 'ldap' : 'manual',
                'active'         => 1,
                'created'        => $r->data_registrazione ?: $this->now(),
                'modified'       => $this->now(),
            ];

            if ($createUsers) {
                $data['user_id'] = $this->ensureJoomlaUser($uid, $data, $roles[mb_strtolower($uid)] ?? [], $groups, $registered, $authorScope);
            }

            $this->upsert('#__protis_people', $data, array_values(array_diff(array_keys($data), ['uid', 'created'])));
            $id = (int) $this->db->setQuery('SELECT id FROM #__protis_people WHERE uid = ' . $this->db->quote($uid))->loadResult();

            $this->personByUid[mb_strtolower($uid)] = $id;

            if ($data['matricola']) {
                $this->personByMatricola[$data['matricola']] = $id;
            }

            $this->count('persone');
        }
    }

    private function ensureJoomlaUser(string $uid, array $p, array $wpRoles, array $groups, int $registered, string $authorScope): ?int
    {
        $id = (int) UserHelper::getUserId($uid);

        if (!$id) {
            $email = $p['email'] !== '' ? $p['email'] : $uid . '@cnr.it';

            // email duplicata: Joomla richiede email univoche
            if ((int) $this->db->setQuery('SELECT COUNT(*) FROM #__users WHERE email = ' . $this->db->quote($email))->loadResult() > 0) {
                $email = $uid . '+protis@cnr.it';
            }

            $user  = new User();
            $props = [
                'name'     => trim(ucwords(mb_strtolower($p['nome'] . ' ' . $p['cognome']))) ?: $uid,
                'username' => $uid,
                'email'    => $email,
                'password' => UserHelper::genRandomPassword(32),
                'block'    => 0,
                'groups'   => [$registered],
            ];
            $user->bind($props);

            if (!$user->save()) {
                $this->warn("Account Joomla $uid non creato: " . implode('; ', $user->getErrors()));

                return null;
            }

            $id = (int) $user->id;
            $this->count('account_joomla');
        }

        $target = null;

        if (array_intersect($wpRoles, ['administrator'])) {
            $target = 'PROTIS Amministratori';
        } elseif (array_intersect($wpRoles, ['author', 'editor', 'responsabile', 'contributor'])) {
            $target = 'PROTIS Responsabili';
        }

        if ($target && isset($groups[$target])) {
            UserHelper::addUserToGroup($id, (int) $groups[$target]);
        }

        return $id;
    }

    private static function normCup(string $cup): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $cup));
    }

    private function importProjects(): void
    {
        $list = $this->proj->setQuery('SELECT * FROM elencoprogetti ORDER BY id')->loadObjectList();

        foreach ($list as $e) {
            $table = $e->nome_progetto;

            try {
                $p = $this->proj->setQuery('SELECT * FROM ' . $this->proj->quoteName($table) . ' LIMIT 1')->loadAssoc();
            } catch (\Throwable) {
                $this->warn("Tabella progetto $table mancante: saltato");
                continue;
            }

            if (!$p) {
                $this->warn("Tabella progetto $table vuota: saltato");
                continue;
            }

            $legacyId = (string) $e->id_progetto;
            $acr      = strtoupper(trim((string) $p['acronimo']));
            // nella V.2 l'intestazione SAL era scritta nel codice per alcuni progetti: indicarli con --sal=ACR1,ACR2
            $isSal    = \in_array($acr, $this->salProjects, true);

            $row = (object) [
                'legacy_id'       => $legacyId,
                'acronimo'        => trim((string) $p['acronimo']),
                'titolo'          => (string) $p['titolo'],
                'codice_avviso'   => (string) $p['codice_avviso'],
                'cup'             => strtoupper(preg_replace('/\s+/', '', (string) $p['cup'])),
                'ente'            => (string) $p['ente'],
                'codice_progetto' => (string) $p['descrizione'],
                'note'            => (string) $p['note'],
                'data_inizio'     => self::date($p['data_inizio'] ?? null),
                'data_fine'       => self::date($p['data_fine'] ?? null),
                'data_proroga'    => self::date($p['data_proroga'] ?? null),
                'layout'          => $isSal ? 'sal' : 'standard',
                'partita_iva'     => $isSal ? '02118311006' : '',
                'sal_numero'      => $isSal ? '1' : '',
                'monte_ore_annuo' => $isSal ? '1506' : '',
                'published'       => 1,
                'created'         => $e->created_at ?: $this->now(),
                'modified'        => $this->now(),
            ];

            // codici solo numerici: Grant Agreement, non CUP
            if (preg_match('/^\d+$/', $row->cup)) {
                $row->grant_agreement = $row->cup;
                $row->cup             = '';
            }

            $existingId = (int) $this->db->setQuery('SELECT id FROM #__protis_projects WHERE legacy_id = ' . $this->db->quote($legacyId))->loadResult();

            if ($existingId) {
                $row->id = $existingId;
                $this->db->updateObject('#__protis_projects', $row, 'id');
            } else {
                $this->db->insertObject('#__protis_projects', $row, 'id');
            }

            $pid = (int) $row->id;

            // logo
            if (!empty($p['logo']) && $this->legacyPath) {
                $src = rtrim($this->legacyPath, '/') . '/admin/listofproject/' . $table . '/' . $p['logo'];

                if (is_file($src)) {
                    $dir = ProjectService::logoDir();

                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }

                    $ext  = strtolower(pathinfo($src, PATHINFO_EXTENSION));
                    $name = 'progetto_' . $pid . '_v2.' . $ext;
                    copy($src, $dir . '/' . $name);
                    $this->db->setQuery('UPDATE #__protis_projects SET logo = ' . $this->db->quote($name) . ' WHERE id = ' . $pid)->execute();
                } else {
                    $this->warn("Logo non trovato per {$row->acronimo}: $src");
                }
            }

            // WP: colonne wp_N
            $ordering = 0;
            $wpCodes  = array_filter(array_keys($p), static fn ($k) => preg_match('/^wp_\d+$/', $k));
            usort($wpCodes, static fn ($a, $b) => (int) substr($a, 3) <=> (int) substr($b, 3));

            foreach ($wpCodes as $code) {
                $label = trim((string) $p[$code]);
                $ordering++;

                $wpId = (int) $this->db->setQuery(
                    'SELECT id FROM #__protis_workpackages WHERE project_id = ' . $pid . ' AND code = ' . $this->db->quote($code)
                )->loadResult();

                $wp = (object) ['project_id' => $pid, 'code' => $code, 'label' => $label !== '' ? $label : strtoupper(str_replace('_', ' ', $code)), 'ordering' => $ordering, 'published' => 1];

                if ($wpId) {
                    $wp->id = $wpId;
                    $this->db->updateObject('#__protis_workpackages', $wp, 'id');
                } else {
                    $this->db->insertObject('#__protis_workpackages', $wp, 'id');
                    $wpId = (int) $wp->id;
                }

                $this->wpByCode[$pid][$code] = $wpId;
                $this->count('work_package');
            }

            $this->projectsByCup[self::normCup($row->cup)][] = $pid;
            $this->count('progetti');
        }

        foreach ($this->projectsByCup as $cup => $ids) {
            if (\count($ids) > 1) {
                $names = $this->db->setQuery('SELECT acronimo FROM #__protis_projects WHERE id IN (' . implode(',', $ids) . ')')->loadColumn();
                $this->warn('CUP duplicato ' . strtoupper($cup) . ' tra progetti: ' . implode(', ', $names)
                    . '. In V.2 le ore erano indistinguibili: verranno attribuite in base alle associazioni della persona.');
            }
        }
    }

    private function importAssignments(): void
    {
        $projByLegacy = $this->db->setQuery('SELECT legacy_id, id FROM #__protis_projects WHERE legacy_id IS NOT NULL')->loadAssocList('legacy_id', 'id');
        $rows         = $this->proj->setQuery('SELECT * FROM utenti_progetti ORDER BY id')->loadObjectList();

        foreach ($rows as $r) {
            $personId = $this->findPerson((string) $r->matricola, (string) $r->uid);
            $pid      = (int) ($projByLegacy[(string) $r->id_progetto] ?? 0);

            if (!$personId || !$pid) {
                $this->warn("Associazione {$r->uid} → {$r->acronimo} non importata (persona o progetto mancanti)");
                continue;
            }

            $layout = match (strtolower(trim((string) $r->nota2))) {
                'vista1' => 'horizon',
                'vista2' => 'mise',
                default  => '',
            };

            $this->upsert('#__protis_assignments', [
                'person_id'    => $personId,
                'project_id'   => $pid,
                'monteore_min' => Time::hoursStringToMinutes((string) $r->monteore),
                'layout'       => $layout,
                'nota'         => (string) $r->nota1,
                'created'      => $this->now(),
            ], ['monteore_min', 'layout', 'nota']);

            $this->assignedProjects[$personId][] = $pid;
            $this->count('associazioni');
        }
    }

    private function findPerson(string $matricola, string $uid): int
    {
        $matricola = trim($matricola);

        if ($matricola !== '' && isset($this->personByMatricola[$matricola])) {
            return $this->personByMatricola[$matricola];
        }

        return $this->personByUid[mb_strtolower(trim($uid))] ?? 0;
    }

    private function importCalendars(): void
    {
        $tables = $this->proj->setQuery("SHOW TABLES LIKE 'calendario\\_%'")->loadColumn();

        foreach ($tables as $table) {
            $calId = substr($table, \strlen('calendario_'));
            $personId = $this->personByMatricola[$calId] ?? ($this->personByUid[mb_strtolower($calId)] ?? 0);

            $cols   = $this->proj->setQuery('SHOW COLUMNS FROM ' . $this->proj->quoteName($table))->loadColumn();
            $wpCols = array_values(array_filter($cols, static fn ($c) => preg_match('/_wp_\d+$/i', $c)));
            $rows   = $this->proj->setQuery('SELECT * FROM ' . $this->proj->quoteName($table) . ' ORDER BY data')->loadAssocList();

            if (!$personId && $rows) {
                $personId = $this->personByUid[mb_strtolower((string) $rows[0]['uid'])] ?? 0;
            }

            if (!$personId) {
                $this->warn("Calendario $table: persona non trovata, saltato");
                continue;
            }

            // mappa colonna → wp_id
            $colMap = [];

            foreach ($wpCols as $c) {
                if (!preg_match('/^(.+)_(wp_\d+)$/i', $c, $m)) {
                    continue;
                }

                $wpId = $this->resolveWp($personId, strtolower($m[1]), strtolower($m[2]), $table);

                if ($wpId) {
                    $colMap[$c] = $wpId;
                }
            }

            $person = $this->db->setQuery('SELECT matricola FROM #__protis_people WHERE id = ' . $personId)->loadResult();
            $jsonCache = [];
            $nDays = 0;
            $nHours = 0;

            foreach ($rows as $r) {
                $date = (string) $r['data'];

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date === '0000-00-00') {
                    continue;
                }

                $day = [
                    'person_id'   => $personId,
                    'day'         => $date,
                    'worked_min'  => Time::toMinutes($r['tempo_lavorato'] ?? null),
                    'smart_min'   => Time::toMinutes($r['smartworking'] ?? null),
                    'mission_min' => Time::toMinutes($r['missione'] ?? null),
                    'sick_min'    => Time::toMinutes($r['malattia'] ?? null),
                    'holiday_min' => Time::toMinutes($r['ferie'] ?? null),
                    'permit_min'  => Time::toMinutes($r['permessi'] ?? null),
                    'other_min'   => Time::toMinutes($r['altro'] ?? null),
                    'source'      => $person ? 'epas' : 'manual',
                    'modified'    => $this->now(),
                ];

                // codici di assenza dal JSON ePAS salvato dalla V.2 (se disponibile)
                $codes = $this->legacyJsonDay($calId, $date, $jsonCache);

                if ($codes !== null) {
                    $day['absence_codes'] = substr(implode(',', $codes), 0, 255);
                    $day['blocked']       = 0;

                    foreach ($codes as $c) {
                        if (!empty($this->absenceCodes[$c]->blocks_fill)) {
                            $day['blocked'] = 1;
                        }
                    }
                } else {
                    $day['blocked'] = ($day['sick_min'] + $day['holiday_min'] + $day['permit_min'] + $day['other_min']) > 0 ? 1 : 0;
                }

                $hasData = $day['worked_min'] + $day['smart_min'] + ($day['tele_min'] ?? 0) + $day['mission_min'] + $day['sick_min'] + $day['holiday_min'] + $day['permit_min'] + $day['other_min'] > 0
                    || !empty($day['absence_codes']);

                if ($hasData) {
                    $this->upsert('#__protis_days', $day, array_values(array_diff(array_keys($day), ['person_id', 'day'])));
                    $nDays++;
                }

                foreach ($colMap as $col => $wpId) {
                    $min = Time::toMinutes($r[$col] ?? null);

                    if ($min > 0) {
                        $this->upsert('#__protis_hours', [
                            'person_id' => $personId,
                            'day'       => $date,
                            'wp_id'     => $wpId,
                            'minutes'   => $min,
                            'modified'  => $this->now(),
                        ], ['minutes', 'modified']);
                        $nHours++;
                    }
                }
            }

            $this->say(\sprintf('  %s → %d giorni, %d righe ore', $table, $nDays, $nHours));
            $this->count('giorni', $nDays);
            $this->count('ore', $nHours);
        }
    }

    private function resolveWp(int $personId, string $cup, string $code, string $table): ?int
    {
        $cupNorm    = self::normCup($cup);
        $candidates = $this->projectsByCup[$cupNorm] ?? [];

        // vecchia nomenclatura senza underscore
        if (!$candidates) {
            foreach ($this->projectsByCup as $k => $ids) {
                if (str_replace('_', '', $k) === str_replace('_', '', $cupNorm)) {
                    $candidates = $ids;
                    break;
                }
            }
        }

        if (!$candidates) {
            $this->warn("$table: colonna {$cup}_{$code} senza progetto corrispondente (CUP sconosciuto)");

            return null;
        }

        $pid = $candidates[0];

        if (\count($candidates) > 1) {
            $mine = array_values(array_intersect($candidates, $this->assignedProjects[$personId] ?? []));

            if (\count($mine) === 1) {
                $pid = $mine[0];
            } else {
                $this->warn("$table: colonna {$cup}_{$code} ambigua (CUP condiviso), attribuita al progetto #$pid: verificare");
                $pid = $mine[0] ?? $pid;
            }
        }

        if (!isset($this->wpByCode[$pid][$code])) {
            $this->warn("$table: WP $code non presente nel progetto #$pid");

            return null;
        }

        return $this->wpByCode[$pid][$code];
    }

    private const MONTHS_IT = [1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

    /** @return string[]|null codici assenza del giorno dal JSON V.2, null se il file non esiste */
    private function legacyJsonDay(string $calId, string $date, array &$cache): ?array
    {
        if (!$this->legacyPath) {
            return null;
        }

        $y   = (int) substr($date, 0, 4);
        $m   = (int) substr($date, 5, 2);
        $key = "$y-$m";

        if (!\array_key_exists($key, $cache)) {
            $file        = rtrim($this->legacyPath, '/') . "/admin/calendario/JSON/$calId/$y/" . self::MONTHS_IT[$m] . "_$y.json";
            $cache[$key] = null;

            if (is_file($file)) {
                $json = json_decode((string) file_get_contents($file), true);

                foreach ($json['personDays'] ?? [] as $pd) {
                    $cache[$key][substr((string) $pd['date'], 0, 10)] = array_values(array_filter(array_map(
                        static fn ($a) => strtoupper(trim((string) ($a['code'] ?? ''))),
                        $pd['absences'] ?? []
                    )));
                }

                $cache[$key] ??= [];
            }
        }

        if ($cache[$key] === null) {
            return null;
        }

        return $cache[$key][$date] ?? [];
    }

    private static function date(?string $d): ?string
    {
        return $d && preg_match('/^\d{4}-\d{2}-\d{2}/', $d) && !str_starts_with($d, '0000') ? substr($d, 0, 10) : null;
    }
}
