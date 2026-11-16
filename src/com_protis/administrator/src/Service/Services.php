<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Micro-registro dei servizi PROTIS IBF (istanze condivise per richiesta).
 */
final class Services
{
    private static array $i = [];

    public static function people(): PeopleService
    {
        return self::$i[__FUNCTION__] ??= new PeopleService();
    }

    public static function projects(): ProjectService
    {
        return self::$i[__FUNCTION__] ??= new ProjectService();
    }

    public static function calendar(): CalendarService
    {
        return self::$i[__FUNCTION__] ??= new CalendarService();
    }

    public static function epas(): EpasService
    {
        return self::$i[__FUNCTION__] ??= new EpasService();
    }

    public static function timesheet(): TimesheetService
    {
        return self::$i[__FUNCTION__] ??= new TimesheetService(self::people(), self::projects(), self::calendar());
    }

    public static function report(): ReportService
    {
        return self::$i[__FUNCTION__] ??= new ReportService();
    }

    public static function roles(): RoleService
    {
        return self::$i[__FUNCTION__] ??= new RoleService();
    }

    public static function settings(): SettingsService
    {
        return self::$i[__FUNCTION__] ??= new SettingsService();
    }
}
