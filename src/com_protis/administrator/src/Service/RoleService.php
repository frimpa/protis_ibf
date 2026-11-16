<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Acl;
use Joomla\CMS\Access\Access;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;

/**
 * Ruolo PROTIS IBF di una persona, cioè i gruppi Joomla del suo account:
 *  - user    → Registered (utente normale)
 *  - manager → in più "PROTIS Responsabili" (utente amministrativo: gestisce progetti, persone, associazioni)
 *  - admin   → PROTIS Amministratori o Super User: si cambia solo dall'amministrazione di Joomla
 */
class RoleService extends AbstractService
{
    public const MANAGERS = 'PROTIS Responsabili';

    public const LABELS = ['user' => 'Utente', 'manager' => 'Responsabile (amministrativo)', 'admin' => 'Amministratore'];

    /** Ruolo dell'account Joomla collegato alla persona; null se la persona non ha ancora un account. */
    public function roleOf(object $person): ?string
    {
        if (empty($person->user_id)) {
            return null;
        }

        $user = User::getInstance((int) $person->user_id);

        if (!$user || !$user->id) {
            return null;
        }

        if (Acl::isAdmin($user)) {
            return 'admin';
        }

        return \in_array($this->groupId(), Access::getGroupsByUser((int) $user->id, false), true) ? 'manager' : 'user';
    }

    /** Assegna "user" o "manager". Gli amministratori non si modificano da qui. */
    public function setRole(object $person, string $role, object $me): string
    {
        if (!\in_array($role, ['user', 'manager'], true)) {
            throw new \InvalidArgumentException('Ruolo non valido.');
        }

        $current = $this->roleOf($person);

        if ($current === null) {
            throw new \DomainException('La persona non ha ancora un account: il ruolo si assegna dopo il suo primo accesso.');
        }

        if ($current === 'admin') {
            throw new \DomainException('Gli amministratori si gestiscono dall\'amministrazione di Joomla.');
        }

        if ((int) $person->id === (int) $me->id) {
            throw new \DomainException('Non puoi modificare il tuo ruolo.');
        }

        $gid = $this->groupId();

        if (!$gid) {
            throw new \RuntimeException('Gruppo "' . self::MANAGERS . '" non trovato: reinstallare il pacchetto PROTIS IBF.');
        }

        if ($role === 'manager' && $current !== 'manager') {
            UserHelper::addUserToGroup((int) $person->user_id, $gid);
        } elseif ($role === 'user' && $current === 'manager') {
            UserHelper::removeUserFromGroup((int) $person->user_id, $gid);
        }

        Access::clearStatics();
        $this->log('person.role', trim($person->cognome . ' ' . $person->nome) . ': ' . self::LABELS[$role], (int) $person->id);

        return $role;
    }

    /** Ruoli di più persone in una sola lettura (per l'elenco Persone). @return array<int,string> person_id => ruolo */
    public function rolesFor(array $people): array
    {
        $out = [];

        foreach ($people as $p) {
            $r = $this->roleOf($p);

            if ($r !== null && $r !== 'user') {
                $out[(int) $p->id] = $r;
            }
        }

        return $out;
    }

    private function groupId(): int
    {
        static $id = null;

        return $id ??= (int) $this->db->setQuery(
            $this->db->createQuery()->select('id')->from('#__usergroups')->where('title = ' . $this->db->quote(self::MANAGERS))
        )->loadResult();
    }
}
