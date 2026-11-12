<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Cifratura credenziali ePAS (libsodium secretbox, stesso formato della V.2: base64(nonce|cipher)).
 *
 * La chiave V.3 è derivata dal "secret" di configuration.php: non serve più il file .env/configKEY.php.
 */
final class Crypto
{
    private static function key(): string
    {
        $secret = (string) Factory::getApplication()->get('secret');

        return sodium_crypto_generichash('protis-ibf-v3|' . $secret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public static function encrypt(string $plain, ?string $key = null): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key ?? self::key()));
    }

    public static function decrypt(?string $encoded, ?string $key = null): ?string
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }

        $raw = base64_decode($encoded, true);

        if ($raw === false || \strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce  = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain  = sodium_crypto_secretbox_open($cipher, $nonce, $key ?? self::key());

        return $plain === false ? null : $plain;
    }
}
