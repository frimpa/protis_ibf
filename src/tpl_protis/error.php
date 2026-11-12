<?php

/**
 * @copyright   (C) CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 *
 * @var \Joomla\CMS\Document\ErrorDocument $this
 */

\defined('_JEXEC') or die;

$code = (int) $this->error->getCode();
?>
<!DOCTYPE html>
<html lang="<?= $this->language ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $code ?> - PROTIS IBF</title>
    <style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;background:#f4f6fa;color:#1d2433;text-align:center;padding:16px}
    a{display:inline-block;margin-top:1rem;background:#0b4f8a;color:#fff;padding:.7rem 1.2rem;border-radius:10px;text-decoration:none}</style>
</head>
<body>
<main>
    <h1><?= $code ?: 'Errore' ?></h1>
    <p><?= htmlspecialchars($code === 404 ? 'Pagina non trovata.' : ($code === 403 ? 'Accesso non consentito.' : 'Si è verificato un errore.')) ?></p>
    <?php if (JDEBUG) : ?><pre style="text-align:left;white-space:pre-wrap"><?= htmlspecialchars($this->error->getMessage()) ?></pre><?php endif; ?>
    <a href="<?= \Joomla\CMS\Uri\Uri::root() ?>index.php?option=com_protis">Torna a PROTIS IBF</a>
</main>
</body>
</html>
