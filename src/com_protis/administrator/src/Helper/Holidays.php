<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Festività nazionali italiane + santo patrono della sede (per evidenziare i giorni nel calendario
 * degli utenti senza timbrature ePAS).
 */
final class Holidays
{
    /** Santo patrono per città della sede (nome della sede in minuscolo, MM-DD) */
    public const PATRONS = [
        'ancona'   => ['05-04', "San Ciriaco"],
        'bari'     => ['12-06', 'San Nicola'],
        'bologna'  => ['10-04', 'San Petronio'],
        'cagliari' => ['10-30', 'San Saturnino'],
        'catania'  => ['02-05', "Sant'Agata"],
        'firenze'  => ['06-24', 'San Giovanni Battista'],
        'genova'   => ['06-24', 'San Giovanni Battista'],
        'messina'  => ['06-03', 'Madonna della Lettera'],
        'milano'   => ['12-07', "Sant'Ambrogio"],
        'napoli'   => ['09-19', 'San Gennaro'],
        'padova'   => ['06-13', "Sant'Antonio"],
        'palermo'  => ['07-15', 'Santa Rosalia'],
        'perugia'  => ['01-29', 'San Costanzo'],
        'pisa'     => ['06-17', 'San Ranieri'],
        'potenza'  => ['05-30', 'San Gerardo'],
        'roma'     => ['06-29', 'Santi Pietro e Paolo'],
        'torino'   => ['06-24', 'San Giovanni Battista'],
        'trento'   => ['06-26', 'San Vigilio'],
        'trieste'  => ['11-03', 'San Giusto'],
        'venezia'  => ['04-25', 'San Marco'],
    ];

    /** @return array<string,string> Y-m-d => descrizione */
    public static function forYear(int $year, ?string $sede = null): array
    {
        $fixed = [
            '01-01' => 'Capodanno',
            '01-06' => 'Epifania',
            '04-25' => 'Festa della Liberazione',
            '05-01' => 'Festa del Lavoro',
            '06-02' => 'Festa della Repubblica',
            '08-15' => 'Ferragosto',
            '11-01' => 'Ognissanti',
            '12-08' => 'Immacolata Concezione',
            '12-25' => 'Natale',
            '12-26' => 'Santo Stefano',
        ];

        // San Francesco d'Assisi torna festa nazionale dal 2026 (verificare il calendario ufficiale)
        if ($year >= 2026) {
            $fixed['10-04'] = "San Francesco d'Assisi";
        }

        $city = mb_strtolower(trim((string) $sede));

        if ($city !== '' && isset(self::PATRONS[$city])) {
            [$md, $label] = self::PATRONS[$city];
            $fixed[$md] ??= 'Patrono: ' . $label . ' (' . ucfirst($city) . ')';
        }

        $out = [];

        foreach ($fixed as $md => $label) {
            $out[$year . '-' . $md] = $label;
        }

        $easter                         = self::easter($year);
        $out[$easter->format('Y-m-d')]  = 'Pasqua';
        $out[$easter->modify('+1 day')->format('Y-m-d')] = "Lunedì dell'Angelo";

        ksort($out);

        return $out;
    }

    private static function easter(int $year): \DateTimeImmutable
    {
        // algoritmo di Meeus/Jones/Butcher (non dipende da ext-calendar)
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTimeImmutable(\sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
