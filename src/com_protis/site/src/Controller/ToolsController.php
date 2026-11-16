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
}
