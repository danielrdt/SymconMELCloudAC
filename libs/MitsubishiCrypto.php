<?php

/**
 * Mitsubishi AC Encryption/Decryption Handler
 * 
 * Implements AES-CBC encryption with ISO 7816-4 padding
 * Based on pymitsubishi/mitsubishi_api.py
 */
class MitsubishiCrypto {
    private $encryptionKey;
    const KEY_SIZE = 16;
    const CIPHER_METHOD = 'AES-128-CBC';
    
    public function __construct($key = "unregistered") {
        // Ensure key is exactly 16 bytes
        if (strlen($key) < self::KEY_SIZE) {
            $key = str_pad($key, self::KEY_SIZE, "\0");
        }
        $this->encryptionKey = substr($key, 0, self::KEY_SIZE);
    }
    
    /**
     * Encrypt payload using AES-CBC with ISO 7816-4 padding
     * 
     * @param string $payload Plain text payload
     * @return string Base64 encoded (IV + encrypted data)
     */
    public function encrypt($payload) {
        // Generate random IV
        $iv = openssl_random_pseudo_bytes(self::KEY_SIZE);
        
        // Apply ISO 7816-4 padding
        $paddedPayload = $this->padISO7816_4($payload, self::KEY_SIZE);
        
        // Encrypt
        $encrypted = openssl_encrypt(
            $paddedPayload,
            self::CIPHER_METHOD,
            $this->encryptionKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );
        
        // Combine IV and encrypted data, then base64 encode
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt base64 encoded payload
     * 
     * @param string $base64Payload Base64 encoded (IV + encrypted data)
     * @return string Decrypted plain text
     */
    public function decrypt($base64Payload) {
        // Decode base64
        $encrypted = base64_decode($base64Payload);
        
        // Extract IV and encrypted data
        $iv = substr($encrypted, 0, self::KEY_SIZE);
        $encryptedData = substr($encrypted, self::KEY_SIZE);
        
        // Decrypt
        $decrypted = openssl_decrypt(
            $encryptedData,
            self::CIPHER_METHOD,
            $this->encryptionKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );
        
        // Remove ISO 7816-4 padding
        return $this->unpadISO7816_4($decrypted);
    }
    
    /**
     * Apply ISO 7816-4 padding
     * 
     * @param string $data Data to pad
     * @param int $blockSize Block size
     * @return string Padded data
     */
    private function padISO7816_4($data, $blockSize) {
        $padLength = $blockSize - (strlen($data) % $blockSize);
        $padding = "\x80" . str_repeat("\x00", $padLength - 1);
        return $data . $padding;
    }
    
    /**
     * Remove ISO 7816-4 padding
     * 
     * @param string $data Padded data
     * @return string Unpadded data
     */
    private function unpadISO7816_4($data) {
        // Find the last 0x80 byte
        $pos = strrpos($data, "\x80");
        if ($pos === false) {
            // Fallback: remove trailing zeros
            return rtrim($data, "\x00");
        }
        
        // Verify that everything after 0x80 is 0x00
        $padding = substr($data, $pos + 1);
        if ($padding !== str_repeat("\x00", strlen($padding))) {
            // Invalid padding, return as-is
            return $data;
        }
        
        return substr($data, 0, $pos);
    }
}
