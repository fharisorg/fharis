<?php

// Encrypt the data to $encrypted using the public key:
// openssl_public_encrypt($data, $encrypted, $pubKey);
// Decrypt the data using the private key and store the results in $decrypted:
// openssl_private_decrypt($encrypted, $decrypted, $privKey);
// Encrypt the data to $encrypted using the private key:
// openssl_private_encrypt($data, $encrypted, $privKey, OPENSSL_PKCS1_PADDING);
// Decrypt the data using the public key and store the results in $decrypted:
// openssl_public_decrypt($encrypted, $decrypted, $pubKey);

function fifu_create_keys($email) {

    require_once(ABSPATH . '/wp-load.php');

    $config = array(
        "digest_alg" => "sha256",
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    );

    // Create the private and public key
    $res = openssl_pkey_new($config);

    // Extract the private key from $res to $privKey
    openssl_pkey_export($res, $privKey);

    // Extract the public key from $res to $pubKey
    $pubKey = openssl_pkey_get_details($res);
    $pubKey = $pubKey["key"] ?? '';

    // Store key
    update_option('fifu_su_email', array(base64_encode($email)), 'no');
    update_option('fifu_su_privkey', array(base64_encode(openssl_encrypt($privKey, "AES-128-ECB", $email . fifu_get_home_url()))), 'no');

    return base64_encode($pubKey);
}

function fifu_create_signature($data) {
    // Recover key
    $email = base64_decode((get_option('fifu_su_email')[0] ?? ''));
    $privKey = openssl_decrypt(base64_decode((get_option('fifu_su_privkey')[0] ?? '')), "AES-128-ECB", $email . fifu_get_home_url());

    // $data is assumed to contain the data to be signed
    // fetch private key from file and ready it
    $pkeyid = openssl_pkey_get_private($privKey);

    // compute signature
    openssl_sign($data, $signature, $privKey, OPENSSL_ALGO_SHA256);

    return base64_encode($signature);
}

function fifu_create_hash($data) {
    $license_key = get_option('fifu_key');
    return hash_hmac('sha256', $data, $license_key);
}

function fifu_get_renew_link() {
    $default = 'https://ws.featuredimagefromurl.com/keys/';

    $key = get_option('fifu_key');
    $email = get_option('fifu_email');

    if (!$key || !$email)
        return $default;

    // Encode email as base64
    $encoded_email = base64_encode($email);

    // Combine encoded email and key
    $data = $encoded_email . '|' . $key;

    // Encryption key - must be exactly 32 bytes
    $encryption_key = get_option('fifu_renew_link_key');

    if (!$encryption_key)
        return $default;

    $encryption_key = str_pad($encryption_key, 32, '0'); // Ensure the key is 32 bytes
    // Generate a secure IV
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

    // Apply PKCS7 padding
    $block_size = 16;
    $padding = $block_size - (strlen($data) % $block_size);
    $data .= str_repeat(chr($padding), $padding);

    // Encrypt the data using AES-256-CBC
    $encrypted_data = openssl_encrypt($data, 'aes-256-cbc', $encryption_key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted_data === false) {
        error_log('Encryption failed');
        return $default;
    }

    // Concatenate IV and encrypted data
    $encrypted_data_with_iv = $iv . $encrypted_data;

    // Encode the encrypted data with IV
    $encoded_data = base64_encode($encrypted_data_with_iv);
    $url = $default . 'renew?data=' . urlencode($encoded_data);
    return $url;
}

function fifu_generate_random_key() {
    return md5(uniqid(mt_rand(), true));
}

