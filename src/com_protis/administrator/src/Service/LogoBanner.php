<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Compone i loghi di un progetto in un'unica fascia orizzontale (PNG su fondo bianco):
 * stessa altezza ottica per tutti, spaziatura uniforme, centrati verticalmente.
 */
final class LogoBanner
{
    /** altezza della fascia e dei loghi (px): la fascia va stampata a tutta larghezza A4 orizzontale */
    private const HEIGHT    = 260;
    private const LOGO_H    = 200;
    private const GAP       = 110;
    private const PAD       = 40;
    private const MAX_WIDTH = 4200;

    public static function available(): bool
    {
        return \function_exists('imagecreatetruecolor');
    }

    /**
     * @param  string[]  $files  percorsi assoluti dei loghi, nell'ordine di stampa
     */
    public static function compose(array $files, string $dest): void
    {
        if (!self::available()) {
            throw new \RuntimeException('Estensione PHP GD non disponibile: impossibile comporre la fascia dei loghi.');
        }

        $imgs = [];

        foreach ($files as $f) {
            $im = self::load($f);

            if (!$im) {
                continue;
            }

            $im = self::trim($im);
            $w  = imagesx($im);
            $h  = imagesy($im);
            // loghi molto larghi (già "a fascia") non devono diventare enormi: limite di larghezza relativo
            $scale = min(self::LOGO_H / $h, (self::LOGO_H * 6) / $w);
            $imgs[] = [$im, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        }

        if (!$imgs) {
            throw new \InvalidArgumentException('Nessun logo valido da comporre.');
        }

        $total = array_sum(array_column($imgs, 1)) + self::GAP * (\count($imgs) - 1) + 2 * self::PAD;
        $k     = $total > self::MAX_WIDTH ? self::MAX_WIDTH / $total : 1.0;
        $W     = (int) round($total * $k);
        $H     = (int) round(self::HEIGHT * $k);

        $out   = imagecreatetruecolor($W, $H);
        $white = imagecolorallocate($out, 255, 255, 255);
        imagefill($out, 0, 0, $white);

        $x = self::PAD * $k;

        foreach ($imgs as [$im, $w, $h]) {
            $w = (int) round($w * $k);
            $h = (int) round($h * $k);
            imagecopyresampled($out, $im, (int) round($x), (int) round(($H - $h) / 2), 0, 0, $w, $h, imagesx($im), imagesy($im));
            $x += $w + self::GAP * $k;
            imagedestroy($im);
        }

        if (!imagepng($out, $dest, 6)) {
            throw new \RuntimeException('Impossibile salvare la fascia dei loghi.');
        }

        imagedestroy($out);
    }

    /**
     * Riduce un logo caricato se è più grande del necessario (lato lungo oltre $max px):
     * viene salvato in PNG su sfondo bianco. Restituisce il nome del file (può cambiare estensione).
     */
    public static function shrink(string $path, int $max = 1600): string
    {
        $info = @getimagesize($path);

        if (!self::available() || !$info || max($info[0], $info[1]) <= $max) {
            return basename($path);
        }

        $im = self::load($path);

        if (!$im) {
            return basename($path);
        }

        $r   = $max / max($info[0], $info[1]);
        $w   = max(1, (int) round($info[0] * $r));
        $h   = max(1, (int) round($info[1] * $r));
        $out = imagecreatetruecolor($w, $h);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
        imagedestroy($im);

        $dest = preg_replace('/\.[a-z]+$/i', '', $path) . '.png';

        if (!imagepng($out, $dest, 6)) {
            return basename($path);
        }

        imagedestroy($out);

        if ($dest !== $path) {
            @unlink($path);
        }

        return basename($dest);
    }

    private static function load(string $file): ?\GdImage
    {
        $info = @getimagesize($file);

        if (!$info) {
            return null;
        }

        $src = match ($info[2]) {
            IMAGETYPE_PNG  => @imagecreatefrompng($file),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_GIF  => @imagecreatefromgif($file),
            IMAGETYPE_WEBP => \function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            default        => false,
        };

        if (!$src) {
            return null;
        }

        // appiattisce la trasparenza su bianco
        $w  = imagesx($src);
        $h  = imagesy($src);
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagecopy($im, $src, 0, 0, 0, 0, $w, $h);
        imagedestroy($src);

        return $im;
    }

    /** Rimuove i bordi bianchi/quasi bianchi attorno al logo. */
    private static function trim(\GdImage $im): \GdImage
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $isBg = static function (int $x, int $y) use ($im): bool {
            $c = imagecolorat($im, $x, $y);

            return (($c >> 16) & 255) > 245 && (($c >> 8) & 255) > 245 && ($c & 255) > 245;
        };

        $step = max(1, (int) floor(min($w, $h) / 400));
        $top  = 0;
        $bottom = $h - 1;
        $left = 0;
        $right  = $w - 1;

        $rowEmpty = static function (int $y) use ($w, $step, $isBg): bool {
            for ($x = 0; $x < $w; $x += $step) {
                if (!$isBg($x, $y)) {
                    return false;
                }
            }

            return true;
        };
        $colEmpty = static function (int $x, int $y0, int $y1) use ($step, $isBg): bool {
            for ($y = $y0; $y <= $y1; $y += $step) {
                if (!$isBg($x, $y)) {
                    return false;
                }
            }

            return true;
        };

        while ($top < $bottom && $rowEmpty($top)) {
            $top++;
        }

        while ($bottom > $top && $rowEmpty($bottom)) {
            $bottom--;
        }

        while ($left < $right && $colEmpty($left, $top, $bottom)) {
            $left++;
        }

        while ($right > $left && $colEmpty($right, $top, $bottom)) {
            $right--;
        }

        if ($left === 0 && $top === 0 && $right === $w - 1 && $bottom === $h - 1) {
            return $im;
        }

        $cw  = $right - $left + 1;
        $ch  = $bottom - $top + 1;
        $out = imagecreatetruecolor($cw, $ch);
        imagecopy($out, $im, 0, 0, $left, $top, $cw, $ch);
        imagedestroy($im);

        return $out;
    }
}
