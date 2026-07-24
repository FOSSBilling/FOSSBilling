<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Staff\Entity\Admin;
use Box\Mod\Staff\Entity\AdminGroup;
use Box\Mod\Staff\Repository\AdminGroupMemberRepository;
use Box\Mod\Staff\Repository\AdminGroupRepository;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function staffAdminIdentity(): Admin
{
    $admin = createEntity(Admin::class);

    return $admin;
}

function staffAdminSetEntityId(object $entity, int $id): void
{
    $property = new ReflectionProperty($entity, 'id');
    $property->setValue($entity, $id);
}

test('get di', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('get list', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data = [];

    $queryBuilder = Mockery::mock(Doctrine\ORM\QueryBuilder::class);

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('getSearchQueryBuilder')
    ->atLeast()->once()
    ->andReturn($queryBuilder);

    $resultSet = [
        'list' => [],
    ];
    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('paginateMappedQuery')
    ->atLeast()->once()
    ->andReturn($resultSet);

    $di = container();
    $di['pager'] = $pagerMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->get_list($data);
    expect($result)->toBeArray();
});

test('get', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data['id'] = 1;

    $admin = new Box\Mod\Staff\Entity\Admin();
    staffAdminSetEntityId($admin, 1);

    $adminRepository = Mockery::mock(Box\Mod\Staff\Repository\AdminRepository::class);
    $adminRepository->shouldReceive('find')->once()->with(1)->andReturn($admin);

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('getAdminRepository')
    ->atLeast()->once()
    ->andReturn($adminRepository);
    $serviceMock
    ->shouldReceive('toAdminApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $di = container();

    $api->setService($serviceMock);
    $api->setDi($di);

    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('update', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('update')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('delete', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('delete')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->delete($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('change password', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data = [
        'id' => '1',
        'password' => 'test!23A',
        'password_confirm' => 'test!23A',
    ];

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock
    ->shouldReceive('passwordsMatch')
    ->atLeast()->once();
    $validatorMock
    ->shouldReceive('isPasswordStrong')
    ->atLeast()->once()
    ->andReturn(true);

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('changePassword')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();
    $di['validator'] = $validatorMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->change_password($data);

    expect($result)->toBeTrue();
});

test('change password password do not match', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data = [
        'id' => '1',
        'password' => 'test!23A',
        'password_confirm' => 'test',
    ];

    $di = container();
    $api->setDi($di);
    expect(fn () => $api->change_password($data))
        ->toThrow(FOSSBilling\Exception::class, 'Passwords do not match');
});

test('change password weak password', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data = [
        'id' => '1',
        'password' => 'weak',
        'password_confirm' => 'weak',
    ];

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock
        ->shouldReceive('passwordsMatch')
        ->atLeast()->once();
    $validatorMock
        ->shouldReceive('isPasswordStrong')
        ->atLeast()->once()
        ->andThrow(new FOSSBilling\InformationException('Password is too weak.'));

    $di = container();
    $di['validator'] = $validatorMock;

    $api->setDi($di);
    expect(fn () => $api->change_password($data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('create', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data = [
        'password' => 'test!23A',
        'email' => 'okay@example.com',
        'name' => 'OkayTest',
        'group_id' => '2',
    ];

    $newStaffId = 1;

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('create')
    ->atLeast()->once()
    ->andReturn($newStaffId);

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock
    ->shouldReceive('isPasswordStrong')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();
    $di['validator'] = $validatorMock;

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();
    $di['tools'] = $toolsMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->create($data);
    expect($result)->toBeInt();
    expect($result)->toEqual($newStaffId);
});

test('group get pairs', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository
    ->shouldReceive('getParentPairs')
    ->atLeast()->once()
    ->andReturn([]);
    $serviceMock
    ->shouldReceive('getAdminGroupRepository')
    ->atLeast()->once()
    ->andReturn($groupRepository);

    $api->setService($serviceMock);
    $result = $api->group_get_pairs([]);
    expect($result)->toBeArray();
});

test('is super administrator returns true when admin is super administrator', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $admin = staffAdminIdentity();
    $admin->id = 1;

    $di = container();
    $di['loggedin_admin'] = $admin;

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
        ->shouldReceive('isSuperAdministrator')
        ->once()
        ->withNoArgs()
        ->andReturn(true);

    $api->setDi($di);
    $api->setService($serviceMock);

    expect($api->is_super_administrator([]))->toBeTrue();
});

test('is super administrator returns false when admin is not super administrator', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $admin = staffAdminIdentity();
    $admin->id = 1;

    $di = container();
    $di['loggedin_admin'] = $admin;

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
        ->shouldReceive('isSuperAdministrator')
        ->once()
        ->withNoArgs()
        ->andReturn(false);

    $api->setDi($di);
    $api->setService($serviceMock);

    expect($api->is_super_administrator([]))->toBeFalse();
});

test('group repository sorts list as tree', function (): void {
    $root = (new AdminGroup())->setName('Root');
    $child = (new AdminGroup())->setName('Child')->setParent($root);
    $sibling = (new AdminGroup())->setName('Sibling')->setParent($root);
    $grandchild = (new AdminGroup())->setName('Grandchild')->setParent($child);
    staffAdminSetEntityId($root, 1);
    staffAdminSetEntityId($child, 2);
    staffAdminSetEntityId($sibling, 3);
    staffAdminSetEntityId($grandchild, 4);

    $query = Mockery::mock(Doctrine\ORM\Query::class);
    $query->shouldReceive('getResult')->once()->andReturn([$root, $child, $sibling, $grandchild]);
    $queryBuilder = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $queryBuilder->shouldReceive('getQuery')->once()->andReturn($query);
    $groupRepository = Mockery::mock(AdminGroupRepository::class)->makePartial();
    $groupRepository->shouldReceive('getSearchQueryBuilder')->once()->andReturn($queryBuilder);

    expect(array_map(static fn (AdminGroup $group): ?string => $group->getName(), $groupRepository->findTreeSorted()))
        ->toEqual(['Root', 'Child', 'Grandchild', 'Sibling']);
});

test('group get list', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $root = (new AdminGroup())->setName('Root');
    $child = (new AdminGroup())->setName('Child')->setParent($root);
    $sibling = (new AdminGroup())->setName('Sibling')->setParent($root);
    $grandchild = (new AdminGroup())->setName('Grandchild')->setParent($child);
    staffAdminSetEntityId($root, 1);
    staffAdminSetEntityId($child, 2);
    staffAdminSetEntityId($sibling, 3);
    staffAdminSetEntityId($grandchild, 4);

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository
    ->shouldReceive('findTreeSorted')
    ->atLeast()->once()
    ->andReturn([$root, $child, $grandchild, $sibling]);
    $serviceMock
    ->shouldReceive('getAdminGroupRepository')
    ->atLeast()->once()
    ->andReturn($groupRepository);

    $api->setService($serviceMock);

    $result = $api->group_get_list([]);
    expect(array_column($result['list'], 'name'))->toEqual(['Root', 'Child', 'Grandchild', 'Sibling']);
    expect($result)->not->toHaveKey('total');
});

test('group create', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data['name'] = 'Prime Group';
    $newGroupId = 1;

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('createGroup')
    ->atLeast()->once()
    ->with('Prime Group', null)
    ->andReturn($newGroupId);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->group_create($data);
    expect($result)->toBeInt();
    expect($result)->toEqual($newGroupId);
});

test('group create ignores permissions parameter', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('createGroup')
    ->once()
    ->with('Prime Group', null)
    ->andReturn(1);

    $api->setDi(container());
    $api->setService($serviceMock);

    expect($api->group_create(['name' => 'Prime Group', 'permissions' => 'nope']))->toBe(1);
});

test('group get', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data['id'] = '1';
    $group = (new AdminGroup())->setName('Support');

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository
    ->shouldReceive('findById')
    ->atLeast()->once()
    ->with(1)
    ->andReturn($group);
    $serviceMock
    ->shouldReceive('getAdminGroupRepository')
    ->atLeast()->once()
    ->andReturn($groupRepository);

    $di = container();

    $api->setIdentity(staffAdminIdentity());
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->group_get($data);
    expect($result)->toBeArray();
    expect($result['name'])->toBe('Support');
});

test('group delete', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data['id'] = '1';
    $group = new AdminGroup();

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository
    ->shouldReceive('findById')
    ->atLeast()->once()
    ->with(1)
    ->andReturn($group);
    $serviceMock
    ->shouldReceive('getAdminGroupRepository')
    ->atLeast()->once()
    ->andReturn($groupRepository);
    $serviceMock
    ->shouldReceive('deleteGroup')
    ->atLeast()->once()
    ->with($group)
    ->andReturn(true);

    $di = container();

    $api->setIdentity(staffAdminIdentity());
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->group_delete($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('group update', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data['id'] = '1';
    $group = new AdminGroup();

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository
    ->shouldReceive('findById')
    ->atLeast()->once()
    ->with(1)
    ->andReturn($group);
    $serviceMock
    ->shouldReceive('getAdminGroupRepository')
    ->atLeast()->once()
    ->andReturn($groupRepository);
    $serviceMock
    ->shouldReceive('updateGroup')
    ->atLeast()->once()
    ->with($group, $data)
    ->andReturn(true);

    $di = container();

    $api->setIdentity(staffAdminIdentity());
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->group_update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('group member add', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data = ['admin_id' => '2', 'group_id' => '3'];
    $admin = staffAdminIdentity();
    $admin->id = 2;
    $group = new AdminGroup();

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository->shouldReceive('findById')->once()->with(3)->andReturn($group);

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock->shouldReceive('getAdminGroupRepository')->once()->andReturn($groupRepository);
    $serviceMock->shouldReceive('addAdminToGroup')->once()->with(Mockery::type(Admin::class), $group)->andReturn(true);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    expect($api->group_member_add($data))->toBeTrue();
});

test('group member remove', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data = ['admin_id' => '2', 'group_id' => '3'];
    $admin = staffAdminIdentity();
    $admin->id = 2;
    $group = new AdminGroup();

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository->shouldReceive('findById')->once()->with(3)->andReturn($group);

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock->shouldReceive('getAdminGroupRepository')->once()->andReturn($groupRepository);
    $serviceMock->shouldReceive('removeAdminFromGroup')->once()->with(Mockery::type(Admin::class), $group)->andReturn(true);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    expect($api->group_member_remove($data))->toBeTrue();
});

test('group member get list', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $admin = new Box\Mod\Staff\Entity\Admin();
    staffAdminSetEntityId($admin, 2);
    $admin->setName('staff');
    $group = new AdminGroup();
    staffAdminSetEntityId($group, 3);
    $member = ['id' => 2, 'email' => 'staff@example.test'];

    $adminRepository = Mockery::mock(Box\Mod\Staff\Repository\AdminRepository::class);
    $adminRepository->shouldReceive('find')->once()->with(2)->andReturn($admin);
    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository->shouldReceive('findById')->once()->with(3)->andReturn($group);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('getMemberIdsInGroup')->once()->with(3)->andReturn([2]);

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock->shouldReceive('getAdminRepository')->once()->andReturn($adminRepository);
    $serviceMock->shouldReceive('getAdminGroupRepository')->once()->andReturn($groupRepository);
    $serviceMock->shouldReceive('getAdminGroupMemberRepository')->once()->andReturn($groupMemberRepository);
    $serviceMock->shouldReceive('toAdminApiArray')->once()->with($admin)->andReturn($member);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    expect($api->group_member_get_list(['group_id' => '3']))->toBe([$member]);
});

test('admin group get list', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $admin = staffAdminIdentity();
    $admin->id = 2;
    $group = (new AdminGroup())->setName('Support');

    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('findGroupsForAdmin')->once()->with(2)->andReturn([$group]);

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock->shouldReceive('getAdminGroupMemberRepository')->once()->andReturn($groupMemberRepository);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->admin_group_get_list(['admin_id' => '2']);
    expect($result)->toBeArray();
    expect($result[0]['name'])->toBe('Support');
});

test('login history get list', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data = [];

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('getActivityAdminHistorySearchQuery')
    ->atLeast()->once()
    ->andReturn(['sqlString', []]);
    $serviceMock
    ->shouldReceive('toActivityAdminHistoryRowApiArray')
    ->once()
    ->with([
        'id' => 1,
        'admin_id' => 2,
        'ip' => '192.0.2.1',
        'created_at' => '2026-01-01 12:00:00',
        'staff_id' => 2,
        'name' => 'Administrator',
        'email' => 'admin@example.test',
    ])
    ->andReturn(['id' => 1]);

    $resultSet = ['list' => [[
        'id' => 1,
        'admin_id' => 2,
        'ip' => '192.0.2.1',
        'created_at' => '2026-01-01 12:00:00',
        'staff_id' => 2,
        'name' => 'Administrator',
        'email' => 'admin@example.test',
    ]]];
    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($resultSet);

    $di = container();
    $di['pager'] = $pagerMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->login_history_get_list($data);
    expect($result['list'])->toBe([['id' => 1]]);
});

test('login history get', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Admin());
    $data['id'] = '1';

    $activityModel = createEntity(Box\Mod\Activity\Entity\ActivityAdminHistory::class);

    $activityRepoMock = Mockery::mock(Box\Mod\Activity\Repository\ActivityAdminHistoryRepository::class);
    $activityRepoMock->shouldReceive('find')->with(1)->andReturn($activityModel);

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('toActivityAdminHistoryApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Activity\Entity\ActivityAdminHistory::class)
        ->andReturn($activityRepoMock);

    $api->setIdentity(staffAdminIdentity());
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->login_history_get($data);
    expect($result)->toBeArray();
});
