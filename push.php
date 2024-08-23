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

$data = [
    'interests' => [$argv[1] ?? $_GET['channel'] ?? 'none'],
    'web' => [
        'notification' => [
            'title' => $argv[2] ?? $_GET['title'] ?? 'none',
            'body' => $argv[3] ?? $_GET['body'] ?? 'none'
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer AAE34A2E2CC914AD332DAA71E9096706B7BF079BB765376413AFE607EF919F06']);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_URL, 'https://5827f13b-206e-47a6-860c-34090e4f382a.pushnotifications.pusher.com/publish_api/v1/instances/5827f13b-206e-47a6-860c-34090e4f382a/publishes');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_exec($ch);
curl_close($ch);
