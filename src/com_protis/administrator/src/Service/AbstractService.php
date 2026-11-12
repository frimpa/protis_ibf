<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

abstract class AbstractService
{
    protected DatabaseInterface $db;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
    }

    protected function now(): string
    {
        return Factory::getDate()->toSql();
    }

    /** INSERT ... ON DUPLICATE KEY UPDATE su una tabella #__protis_* */
    protected function upsert(string $table, array $data, array $updateColumns): void
    {
        $db      = $this->db;
        $columns = array_map([$db, 'quoteName'], array_keys($data));
        $values  = array_map(static fn ($v) => $v === null ? 'NULL' : $db->quote((string) $v), array_values($data));
        $updates = array_map(static fn ($c) => $db->quoteName($c) . ' = VALUES(' . $db->quoteName($c) . ')', $updateColumns);

        $sql = 'INSERT INTO ' . $db->quoteName($table) . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')'
            . ($updates ? ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates) : '');

        $db->setQuery($sql)->execute();
    }

    protected function log(string $action, string $message, ?int $personId = null): void
    {
        try {
            $user = Factory::getApplication()->getIdentity();
            $row  = (object) [
                'created'   => $this->now(),
                'user_id'   => $user ? (int) $user->id : null,
                'person_id' => $personId,
                'action'    => substr($action, 0, 50),
                'message'   => $message,
            ];
            $this->db->insertObject('#__protis_log', $row);
        } catch (\Throwable) {
            // il log non deve mai bloccare l'operazione
        }
    }
}
