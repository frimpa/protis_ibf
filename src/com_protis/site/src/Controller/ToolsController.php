<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\Controller;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Acl;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Strumenti aggiuntivi riservati agli utenti collegati.
 *  - tools.scrivania → l'applicazione Scrivania PDF (file HTML sul server, indicato nelle Opzioni)
 *  - tools.video     → il video di presentazione (pubblico), servito senza rivelarne il percorso
 *  - tools.poster    → l'immagine di anteprima del video, se presente
 */
class ToolsController extends BaseController
{
    public function scrivania(): void
    {
        $app = Factory::getApplication();

        if (Acl::user()->guest) {
            http_response_code(403);
            echo 'Accesso richiesto.';
            $app->close();
        }

        $file = self::scrivaniaFile();

        if ($file === null) {
            http_response_code(404);
            echo 'Scrivania PDF non configurata.';
            $app->close();
        }

        // pagina servita solo a chi è collegato; il browser la conserva per un giorno
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, max-age=86400');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        $app->close();
    }

    /** Percorso del file della Scrivania PDF, se configurato e leggibile. */
    public static function scrivaniaFile(): ?string
    {
        $path = trim((string) ComponentHelper::getParams('com_protis')->get('scrivania_path', ''));

        if ($path === '') {
            return null;
        }

        $real = realpath($path);

        return $real && is_file($real) && is_readable($real) && preg_match('/\.html?$/i', $real) ? $real : null;
    }

    /** Video di presentazione: visibile anche a chi non è collegato, con supporto alle richieste parziali (avanti/indietro). */
    public function video(): void
    {
        $app  = Factory::getApplication();
        $file = self::videoFile();

        if ($file === null) {
            http_response_code(404);
            echo 'Video non disponibile.';
            $app->close();
        }

        self::sendFile($file, preg_match('/\.webm$/i', $file) ? 'video/webm' : 'video/mp4', 'protis_ibf');
        $app->close();
    }

    /** Immagine di anteprima del video (stesso nome del video, .jpg/.jpeg/.png/.webp). */
    public function poster(): void
    {
        $app  = Factory::getApplication();
        $file = self::posterFile();

        if ($file === null) {
            http_response_code(404);
            $app->close();
        }

        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        self::sendFile($file, $types[strtolower(pathinfo($file, PATHINFO_EXTENSION))], 'protis_ibf');
        $app->close();
    }

    /**
     * Percorso del video, se configurato e leggibile. L'opzione "video_path" può essere un percorso assoluto
     * sul server (anche fuori dalla parte pubblica del sito) o relativo alla radice del sito.
     */
    public static function videoFile(): ?string
    {
        $path = trim((string) ComponentHelper::getParams('com_protis')->get('video_path', 'images/presentazione/protis_ibf.mp4'));

        if ($path === '' || preg_match('#^https?://#i', $path)) {
            return null;
        }

        $real = realpath(str_starts_with($path, '/') && !is_file(JPATH_ROOT . $path) ? $path : JPATH_ROOT . '/' . ltrim($path, '/'));

        return $real && is_file($real) && is_readable($real) && preg_match('/\.(mp4|webm)$/i', $real) ? $real : null;
    }

    /** Anteprima accanto al video: stesso nome, estensione immagine. */
    public static function posterFile(): ?string
    {
        $video = self::videoFile();

        if ($video === null) {
            return null;
        }

        $base = preg_replace('/\.[a-z0-9]+$/i', '', $video);

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            if (is_file($base . '.' . $ext) && is_readable($base . '.' . $ext)) {
                return $base . '.' . $ext;
            }
        }

        return null;
    }

    /** Invia un file in streaming, con Range (206) per i video. Il nome mostrato al browser non rivela il percorso. */
    private static function sendFile(string $file, string $type, string $name): void
    {
        $size  = filesize($file);
        $mtime = filemtime($file);
        $start = 0;
        $end   = $size - 1;

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime && !isset($_SERVER['HTTP_RANGE'])) {
            http_response_code(304);
            return;
        }

        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m)) {
            if ($m[1] === '' && $m[2] !== '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                if ($m[2] !== '') {
                    $end = min((int) $m[2], $size - 1);
                }
            }

            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                return;
            }

            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: ' . $type);
        header('Content-Length: ' . ($end - $start + 1));
        header('Accept-Ranges: bytes');
        header('Content-Disposition: inline; filename="' . $name . '.' . $ext . '"');
        header('Cache-Control: public, max-age=86400');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('X-Content-Type-Options: nosniff');

        $fp   = fopen($file, 'rb');
        $left = $end - $start + 1;
        fseek($fp, $start);

        while ($left > 0 && !feof($fp) && !connection_aborted()) {
            $chunk = fread($fp, (int) min(262144, $left));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            flush();
            $left -= \strlen($chunk);
        }

        fclose($fp);
    }
}
