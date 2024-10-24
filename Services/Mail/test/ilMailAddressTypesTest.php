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

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class ilMailAddressTypesTest
 * @author Michael Jansen <mjansen@databay.de>
 */
class ilMailAddressTypesTest extends ilMailBaseTest
{
    protected function setUp(): void
    {
        parent::setUp();
        ilMailCachedAddressType::clearCache();
    }

    /**
     * @return ilGroupNameAsMailValidator&MockObject
     */
    private function createGroupNameAsValidatorMock(): ilGroupNameAsMailValidator
    {
        return $this->getMockBuilder(ilGroupNameAsMailValidator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['validate'])
            ->getMock();
    }

    private function getAddressTypeFactory(ilGroupNameAsMailValidator $groupNameValidatorMock): ilMailAddressTypeFactory
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $rbacsystem = $this->getMockBuilder(ilRbacSystem::class)->disableOriginalConstructor()->getMock();
        $rbacreview = $this->getMockBuilder(ilRbacReview::class)->disableOriginalConstructor()->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();
        $mailingLists = $this->getMockBuilder(ilMailingLists::class)->disableOriginalConstructor()->getMock();
        $roleMailboxSearch = $this->getMockBuilder(ilRoleMailboxSearch::class)->disableOriginalConstructor()->getMock();

        return new ilMailAddressTypeFactory(
            $groupNameValidatorMock,
            $logger,
            $rbacsystem,
            $rbacreview,
            $addressTypeHelper,
            $mailingLists,
            $roleMailboxSearch
        );
    }

    private function getWrappedAddressType(ilMailAddressType $type): ilMailAddressType
    {
        if ($type instanceof ilMailCachedAddressType) {
            $refl = new ReflectionObject($type);
            $inner = $refl->getProperty('inner');
            $inner->setAccessible(true);

            return $inner->getValue($type);
        }

        return $type;
    }

    public function testFactoryWillReturnListAddressTypeForListName(): void
    {
        $groupNameValidatorMock = $this->createGroupNameAsValidatorMock();
        $groupNameValidatorMock->method('validate')->willReturn(true);

        $mailAddressTypeFactory = $this->getAddressTypeFactory($groupNameValidatorMock);

        $result = $mailAddressTypeFactory->getByPrefix(new ilMailAddress('#il_ml_4711', ''), false);

        $this->assertInstanceOf('ilMailMailingListAddressType', $this->getWrappedAddressType($result));
    }

    public function testFactoryWillReturnGroupAddressTypeForGroupName(): void
    {
        $groupNameValidatorMock = $this->createGroupNameAsValidatorMock();
        $groupNameValidatorMock->method('validate')->willReturn(true);

        $mailAddressTypeFactory = $this->getAddressTypeFactory($groupNameValidatorMock);

        $result = $mailAddressTypeFactory->getByPrefix(new ilMailAddress('#MyGroup', ''), false);

        $this->assertInstanceOf('ilMailGroupAddressType', $this->getWrappedAddressType($result));
    }

    public function testFactoryWillReturnLoginOrEmailAddressAddressType(): void
    {
        $groupNameValidatorMock = $this->createGroupNameAsValidatorMock();
        $groupNameValidatorMock->method('validate')->willReturn(false);

        $mailAddressTypeFactory = $this->getAddressTypeFactory($groupNameValidatorMock);

        $result = $mailAddressTypeFactory->getByPrefix(new ilMailAddress('phpunit', ''), false);

        $this->assertInstanceOf('ilMailLoginOrEmailAddressAddressType', $this->getWrappedAddressType($result));
    }

    public function testFactoryWillReturnRoleAddressType(): void
    {
        $groupNameValidatorMock = $this->createGroupNameAsValidatorMock();
        $groupNameValidatorMock->method('validate')->willReturn(false);

        $mailAddressTypeFactory = $this->getAddressTypeFactory($groupNameValidatorMock);

        $result = $mailAddressTypeFactory->getByPrefix(new ilMailAddress('#member', ''), false);

        $this->assertInstanceOf('ilMailRoleAddressType', $this->getWrappedAddressType($result));
    }

    public function testAdminGroupNameIsAValidMailAddressTypes(): void
    {
        $groupNameValidatorMock = $this->createGroupNameAsValidatorMock();
        $groupNameValidatorMock->method('validate')->willReturn(false);

        $mailAddressTypeFactory = $this->getAddressTypeFactory($groupNameValidatorMock);

        $result = $mailAddressTypeFactory->getByPrefix(new ilMailAddress('#il_grp_admin_98', ''), false);

        $this->assertInstanceOf('ilMailRoleAddressType', $this->getWrappedAddressType($result));
    }

    public function testMemberGroupNameIsAValidMailAddressType(): void
    {
        $groupNameValidatorMock = $this->createGroupNameAsValidatorMock();
        $groupNameValidatorMock->method('validate')->willReturn(false);

        $mailAddressTypeFactory = $this->getAddressTypeFactory($groupNameValidatorMock);

        $result = $mailAddressTypeFactory->getByPrefix(new ilMailAddress('#il_grp_member_98', ''), false);

        $this->assertInstanceOf('ilMailRoleAddressType', $this->getWrappedAddressType($result));
    }

    public function testAdminCourseNameIsAValidMailAddressType(): void
    {
        $groupNameValidatorMock = $this->createGroupNameAsValidatorMock();
        $groupNameValidatorMock->method('validate')->willReturn(false);

        $mailAddressTypeFactory = $this->getAddressTypeFactory($groupNameValidatorMock);

        $result = $mailAddressTypeFactory->getByPrefix(new ilMailAddress('#il_crs_admin_98', ''), false);

        $this->assertInstanceOf('ilMailRoleAddressType', $this->getWrappedAddressType($result));
    }

    public function testMemberCourseNameIsAValidMailAddressType(): void
    {
        $groupNameValidatorMock = $this->createGroupNameAsValidatorMock();
        $groupNameValidatorMock->method('validate')->willReturn(false);

        $mailAddressTypeFactory = $this->getAddressTypeFactory($groupNameValidatorMock);

        $result = $mailAddressTypeFactory->getByPrefix(new ilMailAddress('#il_crs_member_98', ''), false);

        $this->assertInstanceOf('ilMailRoleAddressType', $this->getWrappedAddressType($result));
    }

    public function testUserIdCanBeResolvedFromLoginAddress(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $rbacsystem = $this->getMockBuilder(ilRbacSystem::class)->disableOriginalConstructor()->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();

        $addressTypeHelper->expects($this->once())->method('getInstallationHost')->willReturn('ilias');
        $addressTypeHelper->expects($this->once())->method('getUserIdByLogin')->willReturn(4711);

        $type = new ilMailLoginOrEmailAddressAddressType(
            $addressTypeHelper,
            new ilMailAddress('phpunit', 'ilias'),
            $logger,
            $rbacsystem
        );

        $usrIds = $type->resolve();

        $this->assertCount(1, $usrIds);
        $this->assertArrayHasKey(0, $usrIds);
        $this->assertSame(4711, $usrIds[0]);
    }

    public function testNoUserIdCanBeResolvedFromUnknownLoginAddress(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $rbacsystem = $this->getMockBuilder(ilRbacSystem::class)->disableOriginalConstructor()->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();

        $addressTypeHelper->expects($this->once())->method('getInstallationHost')->willReturn('ilias');
        $addressTypeHelper->expects($this->once())->method('getUserIdByLogin')->willReturn(0);

        $type = new ilMailLoginOrEmailAddressAddressType(
            $addressTypeHelper,
            new ilMailAddress('phpunit', 'ilias'),
            $logger,
            $rbacsystem
        );

        $usrIds = $type->resolve();

        $this->assertCount(0, $usrIds);
    }

    public function testNoUserIdCanBeResolvedFromEmailAddress(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $rbacsystem = $this->getMockBuilder(ilRbacSystem::class)->disableOriginalConstructor()->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();

        $addressTypeHelper->expects($this->once())->method('getInstallationHost')->willReturn('ilias');
        $addressTypeHelper->expects($this->once())->method('getUserIdByLogin')->willReturn(0);

        $type = new ilMailLoginOrEmailAddressAddressType(
            $addressTypeHelper,
            new ilMailAddress('mjansen', 'databay.de'),
            $logger,
            $rbacsystem
        );

        $usrIds = $type->resolve();

        $this->assertCount(0, $usrIds);
    }

    public function testAddressCanBeValidatedFromLoginOrEmailAddressType(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();

        $addressTypeHelper->expects($this->atLeast(3))->method('getInstallationHost')->willReturn('ilias');
        $addressTypeHelper->expects($this->exactly(2))->method('getUserIdByLogin')->willReturnOnConsecutiveCalls(
            4711,
            4711,
            0
        );

        $addressTypeHelper->method('receivesInternalMailsOnly')->willReturn(true);

        $rbacsystem = $this->getMockBuilder(ilRbacSystem::class)->disableOriginalConstructor()->getMock();
        $rbacsystem->expects($this->exactly(2))->method('checkAccessOfUser')->willReturnOnConsecutiveCalls(
            true,
            false
        );

        $type = new ilMailLoginOrEmailAddressAddressType(
            $addressTypeHelper,
            new ilMailAddress('phpunit', 'ilias'),
            $logger,
            $rbacsystem
        );
        $this->assertTrue($type->validate(666));
        $this->assertCount(0, $type->getErrors());

        $this->assertFalse($type->validate(666));
        $this->assertArrayHasKey(0, $type->getErrors());
        $this->assertSame('user_cant_receive_mail', $type->getErrors()[0]->getLanguageVariable());

        $type = new ilMailLoginOrEmailAddressAddressType(
            $addressTypeHelper,
            new ilMailAddress('mjansen', 'databay.de'),
            $logger,
            $rbacsystem
        );
        $this->assertTrue($type->validate(666));
        $this->assertCount(0, $type->getErrors());
    }

    public function testUserIdsCanBeResolvedFromGroupNameAddress(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();

        $group = $this->getMockBuilder(ilObjGroup::class)->disableOriginalConstructor()->onlyMethods(['getGroupMemberIds'])->getMock();
        $group->expects($this->once())->method('getGroupMemberIds')->willReturn([666, 777]);

        $addressTypeHelper->expects($this->once())->method('getGroupObjIdByTitle')->willReturn(1);
        $addressTypeHelper->expects($this->once())->method('getAllRefIdsForObjId')->with(1)->willReturn([2]);
        $addressTypeHelper->expects($this->once())->method('getInstanceByRefId')->with(2)->willReturn($group);

        $type = new ilMailGroupAddressType(
            $addressTypeHelper,
            new ilMailAddress('#PhpUnit', ''),
            $logger
        );

        $usrIds = $type->resolve();

        $this->assertCount(2, $usrIds);
    }

    public function testUserIdsCannotBeResolvedFromNonExistingGroupNameAddress(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();

        $group = $this->getMockBuilder(ilObjGroup::class)->disableOriginalConstructor()->onlyMethods(['getGroupMemberIds'])->getMock();
        $group->expects($this->never())->method('getGroupMemberIds');

        $addressTypeHelper->expects($this->once())->method('getGroupObjIdByTitle')->willReturn(0);
        $addressTypeHelper->expects($this->once())->method('getAllRefIdsForObjId')->with(0)->willReturn([]);
        $addressTypeHelper->expects($this->never())->method('getInstanceByRefId');

        $type = new ilMailGroupAddressType(
            $addressTypeHelper,
            new ilMailAddress('#PhpUnit', ''),
            $logger
        );

        $usrIds = $type->resolve();

        $this->assertCount(0, $usrIds);
    }

    public function testValidationFailsForNonExistingGroupNameAddress(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();

        $addressTypeHelper->expects($this->once())->method('doesGroupNameExists')->with('PhpUnit')->willReturn(false);

        $type = new ilMailGroupAddressType(
            $addressTypeHelper,
            new ilMailAddress('#PhpUnit', ''),
            $logger
        );
        $this->assertFalse($type->validate(666));
    }

    public function testValidationSucceedsForExistingGroupName(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();

        $addressTypeHelper->expects($this->once())->method('doesGroupNameExists')->with('PhpUnit')->willReturn(true);

        $type = new ilMailGroupAddressType(
            $addressTypeHelper,
            new ilMailAddress('#PhpUnit', ''),
            $logger
        );
        $this->assertTrue($type->validate(666));
    }

    public function testUserIdsCanBeResolvedFromMailingListAddress(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();

        $list = $this->getMockBuilder(ilMailingList::class)->disableOriginalConstructor()->onlyMethods([
            'getAssignedEntries',
        ])->getMock();
        $list->expects($this->exactly(2))->method('getAssignedEntries')->willReturnOnConsecutiveCalls(
            [['usr_id' => 1], ['usr_id' => 2], ['usr_id' => 3]],
            []
        );

        $lists = $this->getMockBuilder(ilMailingLists::class)->disableOriginalConstructor()->onlyMethods([
            'mailingListExists',
            'getCurrentMailingList',
        ])->getMock();
        $lists->expects($this->exactly(3))->method('mailingListExists')->with('#il_ml_4711')->willReturnOnConsecutiveCalls(
            true,
            true,
            false
        );
        $lists->expects($this->exactly(2))->method('getCurrentMailingList')->willReturn($list);

        $type = new ilMailMailingListAddressType(
            $addressTypeHelper,
            new ilMailAddress('#il_ml_4711', ''),
            $logger,
            $lists
        );

        $usrIds = $type->resolve();

        $this->assertCount(3, $usrIds);

        $usrIds = $type->resolve();

        $this->assertCount(0, $usrIds);

        $usrIds = $type->resolve();

        $this->assertCount(0, $usrIds);
    }

    public function testMailingListAddressCanBeValidated(): void
    {
        $lists = $this->getMockBuilder(ilMailingLists::class)->disableOriginalConstructor()->onlyMethods([
            'mailingListExists',
        ])->getMock();
        $lists->expects($this->exactly(2))->method('mailingListExists')->with('#il_ml_4711')->willReturnOnConsecutiveCalls(
            true,
            false
        );
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();

        $type = new ilMailMailingListAddressType(
            $addressTypeHelper,
            new ilMailAddress('#il_ml_4711', ''),
            $logger,
            $lists
        );

        $this->assertTrue($type->validate(666));
        $this->assertCount(0, $type->getErrors());

        $this->assertFalse($type->validate(666));
        $this->assertCount(1, $type->getErrors());
        $this->assertArrayHasKey(0, $type->getErrors());
        $this->assertSame('mail_no_valid_mailing_list', $type->getErrors()[0]->getLanguageVariable());
    }

    public function testUserIdsCanBeResolvedFromRoleAddress(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $rbacsystem = $this->getMockBuilder(ilRbacSystem::class)->disableOriginalConstructor()->getMock();
        $rbacreview = $this->getMockBuilder(ilRbacReview::class)->disableOriginalConstructor()->onlyMethods(['assignedUsers'])->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();
        $roleMailboxSearch = $this->getMockBuilder(ilRoleMailboxSearch::class)->disableOriginalConstructor()->onlyMethods(['searchRoleIdsByAddressString'])->getMock();

        $roleMailboxSearch->expects($this->once())->method('searchRoleIdsByAddressString')->willReturn([1, 2, 3]);
        $rbacreview->expects($this->exactly(3))->method('assignedUsers')->willReturnOnConsecutiveCalls(
            [4, 5, 6],
            [7, 8],
            [9]
        );

        $type = new ilMailRoleAddressType(
            $addressTypeHelper,
            new ilMailAddress('phpunit', 'ilias'),
            $roleMailboxSearch,
            $logger,
            $rbacsystem,
            $rbacreview
        );

        $usrIds = $type->resolve();

        $this->assertCount(6, $usrIds);
    }

    public function testNoUserIdsCanBeResolvedFromInvalidRoleAddress(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $rbacsystem = $this->getMockBuilder(ilRbacSystem::class)->disableOriginalConstructor()->getMock();
        $rbacreview = $this->getMockBuilder(ilRbacReview::class)->disableOriginalConstructor()->onlyMethods(['assignedUsers'])->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();
        $roleMailboxSearch = $this->getMockBuilder(ilRoleMailboxSearch::class)->disableOriginalConstructor()->onlyMethods(['searchRoleIdsByAddressString'])->getMock();

        $roleMailboxSearch->expects($this->once())->method('searchRoleIdsByAddressString')->willReturn([]);
        $rbacreview->expects($this->never())->method('assignedUsers');

        $type = new ilMailRoleAddressType(
            $addressTypeHelper,
            new ilMailAddress('phpunit', 'ilias'),
            $roleMailboxSearch,
            $logger,
            $rbacsystem,
            $rbacreview
        );

        $usrIds = $type->resolve();

        $this->assertCount(0, $usrIds);
    }

    public function testNoUserIdsCanBeResolvedFromRoleAddressWithoutAnyUsersBeingAssinged(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $rbacsystem = $this->getMockBuilder(ilRbacSystem::class)->disableOriginalConstructor()->getMock();
        $rbacreview = $this->getMockBuilder(ilRbacReview::class)->disableOriginalConstructor()->onlyMethods(['assignedUsers'])->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();
        $roleMailboxSearch = $this->getMockBuilder(ilRoleMailboxSearch::class)->disableOriginalConstructor()->onlyMethods(['searchRoleIdsByAddressString'])->getMock();

        $roleMailboxSearch->expects($this->once())->method('searchRoleIdsByAddressString')->willReturn([1]);
        $rbacreview->expects($this->once())->method('assignedUsers')->willReturn([]);

        $type = new ilMailRoleAddressType(
            $addressTypeHelper,
            new ilMailAddress('phpunit', 'ilias'),
            $roleMailboxSearch,
            $logger,
            $rbacsystem,
            $rbacreview
        );

        $usrIds = $type->resolve();

        $this->assertCount(0, $usrIds);
    }

    public function testValidationForAnonymousUserAsSystemActorSucceedsAlwaysForGlobalRoleAddresses(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $rbacsystem = $this->getMockBuilder(ilRbacSystem::class)->disableOriginalConstructor()->getMock();
        $rbacreview = $this->getMockBuilder(ilRbacReview::class)->disableOriginalConstructor()->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();
        $roleMailboxSearch = $this->getMockBuilder(ilRoleMailboxSearch::class)->disableOriginalConstructor()->onlyMethods(['searchRoleIdsByAddressString'])->getMock();

        $roleMailboxSearch->expects($this->once())->method('searchRoleIdsByAddressString')->willReturnOnConsecutiveCalls([1]);
        $rbacsystem->expects($this->never())->method('checkAccessOfUser');

        $type = new ilMailRoleAddressType(
            $addressTypeHelper,
            new ilMailAddress('phpunit', 'ilias'),
            $roleMailboxSearch,
            $logger,
            $rbacsystem,
            $rbacreview
        );

        $this->assertTrue($type->validate(ANONYMOUS_USER_ID));
        $this->assertCount(0, $type->getErrors());
    }

    public function testPermissionsAreCheckedForRegularUsersWhenValidatingGlobalRoleAddresses(): void
    {
        $logger = $this->getMockBuilder(ilLogger::class)->disableOriginalConstructor()->getMock();
        $rbacsystem = $this->getMockBuilder(ilRbacSystem::class)->disableOriginalConstructor()->onlyMethods(['checkAccessOfUser'])->getMock();
        $rbacreview = $this->getMockBuilder(ilRbacReview::class)->disableOriginalConstructor()->onlyMethods(['isGlobalRole'])->getMock();
        $addressTypeHelper = $this->getMockBuilder(ilMailAddressTypeHelper::class)->getMock();
        $roleMailboxSearch = $this->getMockBuilder(ilRoleMailboxSearch::class)->disableOriginalConstructor()->onlyMethods(['searchRoleIdsByAddressString'])->getMock();

        $roleMailboxSearch->expects($this->exactly(4))->method('searchRoleIdsByAddressString')->willReturnOnConsecutiveCalls(
            [1],
            [],
            [1, 2],
            [1]
        );
        $rbacsystem->expects($this->exactly(4))->method('checkAccessOfUser')->willReturnOnConsecutiveCalls(
            false,
            true,
            true,
            true
        );
        $rbacreview->expects($this->once())->method('isGlobalRole')->with(1)->willReturn(true);

        $type = new ilMailRoleAddressType(
            $addressTypeHelper,
            new ilMailAddress('phpunit', 'ilias'),
            $roleMailboxSearch,
            $logger,
            $rbacsystem,
            $rbacreview
        );

        $this->assertFalse($type->validate(4711));
        $this->assertCount(1, $type->getErrors());
        $this->assertArrayHasKey(0, $type->getErrors());
        $this->assertSame('mail_to_global_roles_not_allowed', $type->getErrors()[0]->getLanguageVariable());

        $this->assertFalse($type->validate(4711));
        $this->assertCount(1, $type->getErrors());
        $this->assertArrayHasKey(0, $type->getErrors());
        $this->assertSame('mail_recipient_not_found', $type->getErrors()[0]->getLanguageVariable());

        $this->assertFalse($type->validate(4711));
        $this->assertCount(1, $type->getErrors());
        $this->assertArrayHasKey(0, $type->getErrors());
        $this->assertSame('mail_multiple_role_recipients_found', $type->getErrors()[0]->getLanguageVariable());

        $this->assertTrue($type->validate(4711));
        $this->assertCount(0, $type->getErrors());
    }

    public function testCacheOnlyResolvesAndValidatesRecipientsOnceIfCachingIsEnabled(): void
    {
        $origin = $this->getMockBuilder(ilMailAddressType::class)->getMock();

        $origin->expects($this->once())->method('resolve');
        $origin->expects($this->once())->method('validate');

        $type = new ilMailCachedAddressType($origin, true);
        $type->resolve();
        $type->resolve();

        $type->validate(6);
        $type->validate(6);
    }

    public function testCacheResolvesAndValidatesRecipientsOnEveryCallIfCachingIsDisabled(): void
    {
        $origin = $this->getMockBuilder(ilMailAddressType::class)->getMock();

        $origin->expects($this->exactly(3))->method('resolve');
        $origin->expects($this->exactly(3))->method('validate');

        $type = new ilMailCachedAddressType($origin, false);
        $type->resolve();
        $type->resolve();
        $type->resolve();

        $type->validate(6);
        $type->validate(6);
        $type->validate(6);
    }
}
