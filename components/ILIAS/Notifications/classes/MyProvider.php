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

use ILIAS\Notifications\Interfaces\PushProviderInterface;
use ilLanguage;

class MyProvider implements PushProviderInterface
{
    public function getIdentifier(): string
    {
        return 'my_prov1';
    }

    public function getName(ilLanguage $lng): string
    {
        return 'Personal Test Provider';
    }

    public function getDescription(ilLanguage $lng): string
    {
        return 'This is a personal test provider for presentation purposes';
    }
}
