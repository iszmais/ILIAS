<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);
$config = json_decode(file_get_contents((isset($argv[3]) ? $argv[3] . '/' : '') . 'subscriptions.json'), true);

$key = $config['public'];
$decoded = bin2hex(base64_decode(str_replace(['-', '_', ''], ['+', '/', '='], $config['private'])));
$pkey = "-----BEGIN EC PRIVATE KEY-----\n" .
    chunk_split(base64_encode(hex2bin('30310201010420' . $decoded . 'a00a06082a8648ce3d030107')), 64, "\n") .
    "-----END EC PRIVATE KEY-----";

$content =  [
    'title' => $argv[1] ?? $_GET['title'] ?? 'none',
    'description' => $argv[2] ?? $_GET['description'] ?? 'none'
];

foreach (($config['subscriptions'] ?? []) as $subscription) {
    $url = $subscription['endpoint'];
    $url_parts = parse_url($url);
    $data = [
        'aud' => $url_parts['scheme'] . '://' . $url_parts['host'],
        'exp' => time() + 3600,
        'sub' => 'mailto:iszmais@databay.de'
    ];

    $jwt_header = base64_url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $jwt_payload = base64_url_encode(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK));
    openssl_sign("$jwt_header.$jwt_payload", $sig, $pkey, 'sha256');
    $components = [];
    $pos = 0;
    $size = strlen($sig);
    while ($pos < $size) {
        $constructed = (ord($sig[$pos]) >> 5) & 0x01;
        $type = ord($sig[$pos++]) & 0x1f;
        $len = ord($sig[$pos++]);
        if ($len & 0x80) {
            $n = $len & 0x1f;
            $len = 0;
            while ($n-- && $pos < $size) $len = ($len << 8) | ord($sig[$pos++]);
        }

        if ($type == 0x03) {
            $pos++;
            $components[] = substr($sig, $pos, $len - 1);
            $pos += $len - 1;
        } else if (! $constructed) {
            $components[] = substr($sig, $pos, $len);
            $pos += $len;
        }
    }
    foreach ($components as &$c) $c = str_pad(ltrim($c, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $jwt_signature =  base64_url_encode(implode('', $components));
    $jwt = "$jwt_header.$jwt_payload.$jwt_signature";


    $encrypted = [];
    exec('node encrypt.js ' . $subscription['keys']['p256dh'] . ' ' . $subscription['keys']['auth'] . ' ' . base64_encode(json_encode($content)), $encrypted);
    $encrypted = join("\n", $encrypted);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'Content-Lenght: ' . strlen($encrypted),
        'Authorization: vapid t=' . $jwt . ', k=' . $key,
        'Ttl: 2419200'
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encrypted);
    $response = curl_exec($ch);

    echo "\nResponse: " . (($response === false) ? 'FALSE' : $response);
    echo "\nError: " . curl_error($ch);
    echo "\n\n";
    curl_close($ch);
}

function base64_url_encode($text):String{
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
}