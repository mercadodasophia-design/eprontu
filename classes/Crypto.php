<?php
/**
 * Helper de criptografia compatível com Flutter CryptoHelper
 * AES-256-CBC com IV de 16 bytes prefixado ao ciphertext e Base64 do conjunto.
 */

class Crypto {
    /**
     * Chave padrão (deve ter 32 bytes para AES-256-CBC)
     */
    public static $defaultKey = '2ece8122bc80db2a816c2df41d6b2a1f';

    /**
     * Criptografa uma string e retorna Base64(IV + ciphertext)
     */
    public static function encryptString(string $plaintext, string $key = null): string {
        $key = $key ?? self::$defaultKey;
        // IV de 16 bytes
        $iv = random_bytes(16);
        // Encriptação em bytes (RAW)
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('Falha na criptografia OpenSSL');
        }
        // Prefixa IV e codifica em base64
        $combined = $iv . $ciphertext;
        return base64_encode($combined);
    }

    /**
     * Decriptografa Base64(IV + ciphertext) e retorna string plaintext
     */
    public static function decryptData(string $encoded, string $key = null): string {
        $key = $key ?? self::$defaultKey;
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || strlen($bytes) < 17) {
            throw new \InvalidArgumentException('Dados inválidos para decriptação');
        }
        // Extrai IV e ciphertext
        $iv = substr($bytes, 0, 16);
        $ciphertext = substr($bytes, 16);
        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new \RuntimeException('Falha na decriptação OpenSSL');
        }
        return $plaintext;
    }
}
?>