<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Conversioni orarie. Internamente tutte le durate sono in minuti interi.
 */
final class Time
{
    public const MONTHS = [
        1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile', 5 => 'Maggio', 6 => 'Giugno',
        7 => 'Luglio', 8 => 'Agosto', 9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre',
    ];

    public const MONTHS_EN = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    /** "HH:MM", "HH:MM:SS", "7,5", "7.5", 450 → minuti */
    public static function toMinutes(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (\is_int($value)) {
            return max(0, $value);
        }

        $value = trim((string) $value);

        if (preg_match('/^(\d{1,4}):(\d{1,2})(?::(\d{1,2}))?$/', $value, $m)) {
            return ((int) $m[1]) * 60 + (int) $m[2];
        }

        if (is_numeric(str_replace(',', '.', $value))) {
            return (int) round(((float) str_replace(',', '.', $value)) * 60);
        }

        return 0;
    }

    /** Monte ore V.2 ("1700", "80 ore", "125,5") → minuti */
    public static function hoursStringToMinutes(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!preg_match('/(\d+(?:[.,]\d+)?)/', $value, $m)) {
            return null;
        }

        return (int) round(((float) str_replace(',', '.', $m[1])) * 60);
    }

    public static function format(int $minutes, bool $emptyIfZero = false): string
    {
        if ($minutes === 0 && $emptyIfZero) {
            return '';
        }

        $sign    = $minutes < 0 ? '-' : '';
        $minutes = abs($minutes);

        return \sprintf('%s%02d:%02d', $sign, intdiv($minutes, 60), $minutes % 60);
    }

    /** minuti → ore come numero (1520, 120,5) */
    public static function hoursNumber(int $minutes): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($minutes / 60, 2, '.', ''), '0'), '.'));
    }

    public static function monthName(int $month, bool $english = false): string
    {
        return ($english ? self::MONTHS_EN : self::MONTHS)[$month] ?? (string) $month;
    }

    /** @return array{0:string,1:string} primo e ultimo giorno del mese (Y-m-d) */
    public static function monthRange(int $year, int $month): array
    {
        $first = \sprintf('%04d-%02d-01', $year, $month);

        return [$first, date('Y-m-t', strtotime($first))];
    }

    public static function daysInMonth(int $year, int $month): int
    {
        return (int) date('t', strtotime(\sprintf('%04d-%02d-01', $year, $month)));
    }
}
