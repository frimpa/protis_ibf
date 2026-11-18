<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Progressive Web App: manifest, service worker e pagina offline.
 * Il service worker è servito da index.php così il suo scope copre tutto il sito.
 */
class PwaController extends BaseController
{
    public const VERSION = '3.27.3';

    private function out(string $type, string $body, int $maxAge = 0): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $type);
        header('Cache-Control: ' . ($maxAge ? 'public, max-age=' . $maxAge : 'no-cache'));
        header('X-Content-Type-Options: nosniff');
        echo $body;
        $this->app->close();
    }

    public function manifest(): void
    {
        $p     = ComponentHelper::getParams('com_protis');
        $root  = Uri::root(true) . '/';
        $icons = Uri::root(true) . '/media/com_protis/images/icons/';
        $name  = (string) $p->get('app_name', 'PROTIS IBF');

        $manifest = [
            'id'               => $root . '?protis',
            'name'             => $name . ' - Timesheet',
            'short_name'       => $name,
            'description'      => trim('Rendicontazione ore e timesheet - ' . $p->get('istituto', ''), ' -'),
            'lang'             => 'it-IT',
            'start_url'        => Route::_('index.php?option=com_protis&view=home', false) . (str_contains(Route::_('index.php?option=com_protis&view=home', false), '?') ? '&' : '?') . 'source=pwa',
            'scope'            => $root,
            'display'          => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation'      => 'any',
            'theme_color'      => (string) $p->get('theme_color', '#0b4f8a'),
            'background_color' => '#f4f6fa',
            'categories'       => ['productivity', 'business'],
            'icons'            => [
                ['src' => $icons . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $icons . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => $icons . 'maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts'        => [
                ['name' => 'Calendario', 'url' => Route::_('index.php?option=com_protis&view=calendar', false), 'icons' => [['src' => $icons . 'icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Timesheet', 'url' => Route::_('index.php?option=com_protis&view=calendar&panel=export', false), 'icons' => [['src' => $icons . 'icon-192.png', 'sizes' => '192x192']]],
            ],
        ];

        $this->out('application/manifest+json; charset=utf-8', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 3600);
    }

    public function sw(): void
    {
        $offline = Route::_('index.php?option=com_protis&task=pwa.offline', false);
        $media   = Uri::root(true) . '/media/com_protis/';
        $version = self::VERSION;

        $precache = json_encode([
            $offline,
            $media . 'css/protis.css?' . $version,
            $media . 'js/protis-app.js?' . $version,
            $media . 'images/icons/icon-192.png',
        ], JSON_UNESCAPED_SLASHES);

        $js = <<<JS
/* PROTIS IBF service worker v{$version} */
const CACHE = 'protis-{$version}';
const OFFLINE = '{$offline}';
const PRECACHE = {$precache};

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('protis-') && k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Mai in cache: API, esportazioni, login e area amministrativa
  if (/task=(api|export|manage)\./.test(url.search) || url.pathname.includes('/administrator/')) return;

  // Asset statici: cache-first con aggiornamento in background
  if (url.pathname.startsWith('{$media}') || url.pathname.includes('/media/')) {
    e.respondWith(
      caches.open(CACHE).then(async (cache) => {
        const hit = await cache.match(req);
        const net = fetch(req).then((res) => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
        return hit || net;
      })
    );
    return;
  }

  // Pagine: rete prima, poi pagina offline (i dati personali non vengono salvati in cache)
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
  }
});
JS;

        header('Service-Worker-Allowed: ' . Uri::root(true) . '/');
        $this->out('application/javascript; charset=utf-8', $js);
    }

    public function offline(): void
    {
        $p     = ComponentHelper::getParams('com_protis');
        $color = htmlspecialchars((string) $p->get('theme_color', '#0b4f8a'));
        $name  = htmlspecialchars((string) $p->get('app_name', 'PROTIS IBF'));
        $icon  = Uri::root(true) . '/media/com_protis/images/icons/icon-192.png';

        $html = <<<HTML
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$name} - offline</title><meta name="theme-color" content="{$color}">
<style>body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f4f6fa;color:#1d2433;display:grid;place-items:center;min-height:100vh;text-align:center;padding:16px}
@media (prefers-color-scheme:dark){body{background:#11151c;color:#e6e9ef}}
img{width:96px;height:96px;border-radius:22px}h1{font-size:1.4rem;margin:.8em 0 .3em}p{opacity:.8;max-width:30em}
button{margin-top:1em;background:{$color};color:#fff;border:0;border-radius:10px;padding:.8em 1.4em;font-size:1rem}</style></head>
<body><main><img src="{$icon}" alt=""><h1>Sei offline</h1><p>{$name} ha bisogno della connessione per leggere e salvare le ore. Controlla la rete e riprova.</p>
<button onclick="location.reload()">Riprova</button></main></body></html>
HTML;

        $this->out('text/html; charset=utf-8', $html);
    }
}
