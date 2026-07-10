<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;


class CustomCrypt
{
    protected string $passphrase, $cipher;

    public function __construct()
    {
        // Retrieve the passphrase from your environment variable
        $this->passphrase = config('services.intellicare.aes_key');
        $this->cipher = config('app.cipher');
    }

    /**
     * Encrypt using CryptoJS/OpenSSL compatible AES-256-CBC
     */
    public function encrypt(string $plainText): string
    {
        // 8-byte random salt
        $salt = random_bytes(8);

        // Derive key & IV
        [$key, $iv] = self::evpKDF($this->passphrase, $salt);

        // Encrypt
        $cipherText = openssl_encrypt(
            $plainText,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        // OpenSSL format:
        // Salted__ + Salt + Ciphertext
        $result =
            "Salted__" .
            $salt .
            $cipherText;

        return base64_encode($result);
    }

    /**
     * Decrypt CryptoJS/OpenSSL compatible AES
     */
    public function decrypt(string $encrypted): string
    {
        $data = base64_decode($encrypted);

        if (substr($data, 0, 8) !== "Salted__") {
            throw new \Exception("Invalid encrypted payload.");
        }

        $salt = substr($data, 8, 8);
        $cipherText = substr($data, 16);

        [$key, $iv] = self::evpKDF($this->passphrase, $salt);

        return openssl_decrypt(
            $cipherText,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    /**
     * OpenSSL EVP_BytesToKey
     */
    private static function evpKDF(string $password, string $salt): array
    {
        $derived = '';
        $block = '';

        while (strlen($derived) < 48) {
            $block = md5($block . $password . $salt, true);
            $derived .= $block;
        }

        $key = substr($derived, 0, 32);
        $iv  = substr($derived, 32, 16);

        return [$key, $iv];
    }
}
