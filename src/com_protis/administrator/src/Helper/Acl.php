<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

/**
 * Ruoli PROTIS IBF mappati sull'ACL Joomla:
 *  - Utente           → qualunque utente autenticato: vede solo il proprio calendario
 *  - Responsabile     → protis.manage: progetti, associazioni, utenti e calendari della propria sede
 *  - Responsabile → protis.manage.all: come sopra ma su tutte le sedi
 *  - Amministratore   → core.admin: impostazioni sedi/ePAS e tutto il resto
 */
final class Acl
{
    public const ASSET = 'com_protis';

    public static function user(): User
    {
        return Factory::getApplication()->getIdentity() ?? new User();
    }

    public static function isAdmin(?User $user = null): bool
    {
        $user ??= self::user();

        return !$user->guest && ($user->authorise('core.admin', self::ASSET) || $user->authorise('core.admin'));
    }

    /** Super utente di Joomla (core.admin globale). */
    public static function isSuperUser(?User $user = null): bool
    {
        $user ??= self::user();

        return !$user->guest && $user->authorise('core.admin');
    }

    public static function canManage(?User $user = null): bool
    {
        $user ??= self::user();

        return !$user->guest && (self::isAdmin($user) || $user->authorise('protis.manage', self::ASSET) || $user->authorise('protis.manage.all', self::ASSET));
    }

    public static function canManageAllSites(?User $user = null): bool
    {
        $user ??= self::user();

        return !$user->guest && (self::isAdmin($user) || $user->authorise('protis.manage.all', self::ASSET));
    }
}
