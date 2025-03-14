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

namespace ILIAS\Notifications\Provider;

use ILIAS\Notifications\ilNotificationPushHandler;
use ILIAS\Notifications\Interfaces\PushProviderInterface;
use ILIAS\Notifications\Model\ilNotificationConfig;
use ILIAS\Notifications\Model\ilNotificationLink;
use ILIAS\Notifications\Model\ilNotificationObject;
use ilLogger;
use ilObjUser;

abstract class NotificationsPushProvider implements PushProviderInterface
{
    protected ilNotificationPushHandler $handler;
    protected ilLogger $logger;

    public function __construct()
    {
        global $DIC;
        $this->logger = $DIC->logger()->root();
        $this->handler = new ilNotificationPushHandler();
    }

    abstract public function getId(): string;
    abstract public function getName(): string;
    abstract public function getDescription(): string;

    public function push(ilObjUser $user, string $title, string $description = '', ilNotificationLink $link = null): void
    {
        if ($user->getPref('push_' . $this->getId()) === '1') {
            $notification = new ilNotificationObject(new ilNotificationConfig(''), $user);
            $notification->title = $title;
            $notification->shortDescription = $description;
            $notification->links = [$link];
            $this->handler->notify($notification);
        } else {
            $this->logger->debug('Notification of type' . $this->getId() . ' not send due to user preferences.');
        }
    }

}
