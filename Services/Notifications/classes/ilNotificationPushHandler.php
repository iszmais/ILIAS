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

namespace ILIAS\Notifications;

use ILIAS\Notifications\Model\ilNotificationObject;
use ILIAS\Notifications\Model\Push\PushSubscription;
use ILIAS\Notifications\Repository\PushRepository;
use ilLogger;
use ilSetting;

class ilNotificationPushHandler extends ilNotificationHandler
{
    protected PushRepository $subscription_repo;
    protected ilLogger $logger;
    protected ilSetting $settings;
    protected string $public_key;
    protected string $private_key;

    public function __construct()
    {
        global $DIC;
        $this->logger = $DIC->logger()->root();
        $this->settings = $DIC->settings();
        $this->subscription_repo = new PushRepository();
        $this->public_key = $DIC->clientIni()->readVariable('push', 'public');
        $this->private_key = $DIC->clientIni()->readVariable('push', 'private');
    }

    /**
     * WIP TODO this is just proof of work. Will be refactored
     */
    public function notify(ilNotificationObject $notification): void
    {
        $decoded = bin2hex(base64_decode(str_replace(['-', '_', ''], ['+', '/', '='], $this->private_key)));
        $pkey = "-----BEGIN EC PRIVATE KEY-----\n" .
            chunk_split(base64_encode(hex2bin('30310201010420' . $decoded . 'a00a06082a8648ce3d030107')), 64, "\n") .
            "-----END EC PRIVATE KEY-----";

        $content = [
            'title' => $notification->title,
            'description' => $notification->shortDescription
        ];

        foreach ($this->subscription_repo->getUserSubscriptions($notification->user->getId()) as $subscription) {
            $url_parts = parse_url($subscription->getEndpoint());
            $data = [
                'sub' => 'mailto:' . $this->settings->get('admin_email'),
                'aud' => $url_parts['scheme'] . '://' . $url_parts['host'],
                'exp' => time() + 3600
            ];

            $jwt_header = $this->base64_url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
            $jwt_payload = $this->base64_url_encode(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK));
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
                    while ($n-- && $pos < $size) {
                        $len = ($len << 8) | ord($sig[$pos++]);
                    }
                }

                if ($type == 0x03) {
                    $pos++;
                    $components[] = substr($sig, $pos, $len - 1);
                    $pos += $len - 1;
                } elseif (! $constructed) {
                    $components[] = substr($sig, $pos, $len);
                    $pos += $len;
                }
            }
            foreach ($components as &$c) {
                $c = str_pad(ltrim($c, "\x00"), 32, "\x00", STR_PAD_LEFT);
            }
            $jwt_signature = $this->base64_url_encode(implode('', $components));
            $jwt = "$jwt_header.$jwt_payload.$jwt_signature";

            $encrypted = [];
            exec('node Services/Notifications/js/encrypt.js ' . $subscription->getP256dh() . ' ' . $subscription->getAuth() . ' ' . base64_encode(json_encode($content)), $encrypted);
            $encrypted = join("\n", $encrypted);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Encoding: aes128gcm',
                'Authorization: vapid t=' . $jwt . ', k=' . $this->public_key,
                'Ttl: 2419200'
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $subscription->getEndpoint());
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encrypted);
            $response = curl_exec($ch);

            $aaa = curl_getinfo($ch);
            if ($response === false) {
                $this->logger->error('Push notification [' . $subscription->getAuth() . '] request failed: ' . curl_error($ch));
            } else {
                $this->handleResponse(curl_getinfo($ch, CURLINFO_HTTP_CODE), $response, $subscription->getAuth());
            }
            curl_close($ch);
        }

    }

    /**
     * This is the lowest common denominator of all popular browsers.
     * For more information see:
     * - https://web.dev/articles/push-notifications-web-push-protocol?hl=de#response-from-push-service
     * - https://autopush.readthedocs.io/en/latest/http.html#error-codes
     * - https://learn.microsoft.com/en-us/windows/apps/design/shell/tiles-and-notifications/push-request-response-headers#response-codes
     * - https://developer.apple.com/documentation/usernotifications/sending-web-push-notifications-in-web-apps-and-browsers
     */
    protected function handleResponse(int $http_code, string $response, string $auth): void
    {
        if ($response !== '') {
            $message = json_decode($response, true)['message'] ?? '';
            if ($message !== '') {
                $this->logger->error("Push notification [$auth] response: $message");
            }
        }
        switch ($http_code) {
            case 200:
            case 201:
                $this->logger->error("Push notification [$auth] successful.");
                break;
            case 400:
            case 401:
            case 403:
                $this->logger->error("Push notification [$auth] request was invalid.");
                break;
            case 404:
            case 410:
                $this->subscription_repo->deleteSubscription($auth);
                $this->logger->error("Push notification [$auth] endpoint outdated. Subscription removed.");
                break;
            case 413:
            case 429:
                $this->logger->error("Push notification [$auth] endpoint blocked due to heavy usage or spam.");
                break;
            default:
                $this->logger->error("Push notification [$auth] went into unkown/browser-specific handling.");
        }
    }

    protected function base64_url_encode($text): String
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }
}
