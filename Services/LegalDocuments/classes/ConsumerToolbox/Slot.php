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

namespace ILIAS\LegalDocuments\ConsumerToolbox;

use ILIAS\LegalDocuments\ConsumerToolbox\ConsumerSlots\OnlineStatusFilter;
use ILIAS\LegalDocuments\ConsumerToolbox\ConsumerSlots\SelfRegistration;
use ILIAS\LegalDocuments\ConsumerToolbox\ConsumerSlots\ModifyFooter;
use ILIAS\LegalDocuments\ConsumerToolbox\ConsumerSlots\Agreement;
use ILIAS\LegalDocuments\ConsumerToolbox\ConsumerSlots\WithdrawProcess;
use ILIAS\LegalDocuments\ConsumerToolbox\ConsumerSlots\ShowOnLoginPage;
use ILIAS\DI\Container;
use ILIAS\LegalDocuments\LazyProvide;
use Closure;
use ilObjUser;

class Slot
{
    public function __construct(
        private readonly string $id,
        private readonly Blocks $blocks,
        private readonly LazyProvide $provide,
        private readonly Container $container
    ) {
    }

    public function showOnLoginPage(): ShowOnLoginPage
    {
        return new ShowOnLoginPage($this->provide, $this->blocks->ui());
    }

    /**
     * @param Closure(): void $after_user_withdrawal
     */
    public function withdrawProcess(User $user, Settings $global_settings, Closure $after_user_withdrawal): WithdrawProcess
    {
        return new WithdrawProcess(
            $user,
            $this->blocks->ui(),
            $this->blocks->routing(),
            $global_settings,
            $this->blocks->retrieveQueryParameter(...),
            $this->provide,
            $after_user_withdrawal
        );
    }

    public function agreement(User $user, Settings $settings): Agreement
    {
        return new Agreement($user, $settings, $this->blocks->ui(), $this->blocks->routing(), $this->blocks->withRequest(...));
    }

    public function modifyFooter(User $user): ModifyFooter
    {
        return new ModifyFooter($this->blocks->ui(), $user, $this->provide, fn($arg) => $this->container->ui()->renderer()->render($arg));
    }

    /**
     * @param Closure(ilObjUser): User $build_user
     */
    public function selfRegistration(User $user, Closure $build_user): SelfRegistration
    {
        return new SelfRegistration(
            $this->id,
            $this->blocks->ui(),
            $user,
            $this->provide,
            fn($arg) => $this->container->ui()->renderer()->render($arg),
            $build_user
        );
    }

    /**
     * @param callable(list<int>): list<int> $select_didnt_agree
     */
    public function onlineStatusFilter(callable $select_didnt_agree): OnlineStatusFilter
    {
        return new OnlineStatusFilter(
            Closure::fromCallable($select_didnt_agree),
            $this->container->rbac()->review()
        );
    }
}
