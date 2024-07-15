<?php

class ZenithEncryption
{
    protected $key, $aes_key;
    public function __construct() {
        $env = parse_ini_file($_SERVER['DOCUMENT_ROOT'].'/../.env');
        $this->key = sodium_hex2bin($env['encryption.key']);
        $this->aes_key = $env['aes.key'];
    }
    /*
    public function encrypt($data = null, $random_nonce = true) {
        if(is_null($data) || !$data || !$this->key) return null;
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        if($random_nonce === false) $nonce = "!++Zenith__Encryption++!";
        $data = sodium_pad($data, 16);
        $ciphertext = $nonce . sodium_crypto_secretbox($data, $nonce, $this->key);
        sodium_memzero($data);
        // sodium_memzero($this->key);
        
        return sodium_bin2hex($ciphertext);
    }

    public function decrypt($data = null) {
        if(is_null($data) || !$data || !$this->key) return null;
        $data = sodium_hex2bin($data);
        $nonce = mb_substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        $data = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
        $data = sodium_unpad($data,16);

        return $data;
    }
    */

    //암호화 함수
    public function encrypt($data)
    {
        $key = substr(hex2bin(openssl_digest($this->aes_key, 'sha512')), 0, 16);
        $enc = @openssl_encrypt($data, "AES-128-ECB", $key, true);
        return strtoupper(bin2hex($enc));
    }

    //복호화 함수
    public function decrypt($data)
    {
        $data = hex2bin($data);
        $key = substr(hex2bin(openssl_digest($this->aes_key, 'sha512')), 0, 16);
        $dec = @openssl_decrypt($data, "AES-128-ECB", $key, true);
        return $dec;
    }
}
?>