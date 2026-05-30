<?php
/**
 * Chiffrement au repos des hash de mot de passe dans inscription_requests (demandes en attente).
 *
 * Sans la clé serveur (fichier sous MONCINE_DATA), une copie de la base seule ne permet pas
 * de retrouver le mot de passe pour créer le compte.
 */

declare(strict_types=1);

namespace Moncine;

final class RegistrationPasswordCipher
{
    private const PREFIX = 'enc1:';

    public static function encryptHash(string $passwordHash): string
    {
        if ($passwordHash === '') {
            return '';
        }

        if (!self::canUseSodium()) {
            return $passwordHash;
        }

        $key = self::loadKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($passwordHash, $nonce, $key);

        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    /**
     * Retourne un hash utilisable par password_verify / createWithPasswordHash, ou null si invalide.
     */
    public static function decryptStored(string $stored): ?string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }

        if (!str_starts_with($stored, self::PREFIX)) {
            return $stored;
        }

        if (!self::canUseSodium()) {
            return null;
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return null;
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, self::loadKey());
        if ($plain === false) {
            return null;
        }

        return $plain;
    }

    private static function canUseSodium(): bool
    {
        return function_exists('sodium_crypto_secretbox');
    }

    /** @return non-empty-string */
    private static function loadKey(): string
    {
        $dir = MONCINE_DATA . '/.keys';
        $path = $dir . '/registration_password.key';

        if (is_file($path)) {
            $key = file_get_contents($path);
            if (is_string($key) && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return $key;
            }
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        file_put_contents($path, $key, LOCK_EX);
        @chmod($path, 0600);

        return $key;
    }
}
