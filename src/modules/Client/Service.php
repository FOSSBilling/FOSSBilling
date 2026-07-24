<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Client\Entity\ClientGroup;
use Box\Mod\Client\Entity\ClientPasswordReset;
use Box\Mod\Client\Repository\ClientBalanceRepository;
use Box\Mod\Client\Repository\ClientGroupRepository;
use Box\Mod\Client\Repository\ClientPasswordResetRepository;
use Box\Mod\Client\Repository\ClientRepository;
use Box\Mod\Staff\Entity\Admin;
use FOSSBilling\i18n;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Locales;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    private ClientRepository $clientRepository;
    private ClientGroupRepository $clientGroupRepository;
    private ClientBalanceRepository $clientBalanceRepository;
    private ClientPasswordResetRepository $clientPasswordResetRepository;

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View Client Details'),
                'description' => __trans('Allows the staff member to view client account details and listings.'),
            ],
            'create' => [
                'type' => 'bool',
                'display_name' => __trans('Create Clients'),
                'description' => __trans('Allows the staff member to create new client accounts.'),
            ],
            'edit_profile' => [
                'type' => 'bool',
                'display_name' => __trans('Edit Client Profiles'),
                'description' => __trans('Allows the staff member to update client profile details and account settings.'),
            ],
            'impersonate_login' => [
                'type' => 'bool',
                'display_name' => __trans('Login as Client'),
                'description' => __trans('Allows the staff member to authenticate as any client account.'),
            ],
            'manage_api_keys' => [
                'type' => 'bool',
                'display_name' => __trans('Manage Client API Keys'),
                'description' => __trans('Allows the staff member to view and generate API keys for client accounts.'),
            ],
            'change_password' => [
                'type' => 'bool',
                'display_name' => __trans('Change Client Passwords'),
                'description' => __trans('Allows the staff member to set new passwords for client accounts.'),
            ],
            'manage_balance' => [
                'type' => 'bool',
                'display_name' => __trans('Manage Client Balance'),
                'description' => __trans('Allows the staff member to add or remove balance entries for client accounts.'),
            ],
            'view_login_history' => [
                'type' => 'bool',
                'display_name' => __trans('View Client Login History'),
                'description' => __trans('Allows the staff member to view client login history and IP addresses.'),
            ],
            'manage_groups' => [
                'type' => 'bool',
                'display_name' => __trans('Manage Client Groups'),
                'description' => __trans('Allows the staff member to create, update, and delete client groups.'),
            ],
            'delete' => [
                'type' => 'bool',
                'display_name' => __trans('Delete Clients'),
                'description' => __trans('Allows the staff member to permanently remove client accounts.'),
            ],
            'bulk_delete' => [
                'type' => 'bool',
                'display_name' => __trans('Bulk Delete Clients'),
                'description' => __trans('Allows the staff member to permanently remove multiple client accounts in a single action.'),
            ],
            'export' => [
                'type' => 'bool',
                'display_name' => __trans('Export Clients'),
                'description' => __trans('Allows the staff member to export client account data.'),
            ],
            'manage_settings' => [
                'type' => 'bool',
                'display_name' => __trans('Manage Client Settings'),
                'description' => __trans('Allows the staff member to manage client module settings and configuration.'),
            ],
        ];
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->clientRepository = $di['em']->getRepository(Client::class);
        $this->clientGroupRepository = $di['em']->getRepository(ClientGroup::class);
        $this->clientBalanceRepository = $di['em']->getRepository(ClientBalance::class);
        $this->clientPasswordResetRepository = $di['em']->getRepository(ClientPasswordReset::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getClientRepository(): ClientRepository
    {
        return $this->clientRepository;
    }

    public function approveClientEmailByHash($hash): bool
    {
        $dbal = $this->di['dbal'];
        $result = $dbal->fetchAssociative('SELECT id, client_id FROM extension_meta WHERE extension = "mod_client" AND meta_key = "confirm_email" AND meta_value = :hash', ['hash' => $hash]);
        if (!$result) {
            throw new InformationException('Invalid email confirmation link');
        }
        $dbal->executeStatement('UPDATE client SET email_approved = 1 WHERE id = :id', ['id' => $result['client_id']]);
        $dbal->executeStatement('DELETE FROM extension_meta WHERE id = :id', ['id' => $result['id']]);

        return true;
    }

    public function generateEmailConfirmationLink($client_id)
    {
        $hash = strtolower((string) $this->di['tools']->generatePassword(50));

        $this->di['dbal']->insert('extension_meta', [
            'extension' => 'mod_client',
            'client_id' => $client_id,
            'meta_key' => 'confirm_email',
            'meta_value' => $hash,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->di['tools']->url('/client/confirm-email/' . $hash);
    }

    public static function onAfterClientSignUp(\Box_Event $event): bool
    {
        $di = $event->getDi();
        $params = $event->getParameters();
        $config = $di['mod_config']('client');
        $emailService = $di['mod_service']('email');

        try {
            $email = [];
            $email['to_client'] = $params['id'];
            $email['code'] = 'mod_client_signup';
            $email['require_email_confirmation'] = false;
            if (isset($config['require_email_confirmation']) && $config['require_email_confirmation']) {
                $clientService = $di['mod_service']('client');
                $email['require_email_confirmation'] = true;
                $email['email_confirmation_link'] = $clientService->generateEmailConfirmationLink($params['id']);
            }

            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send client signup email', ['exception' => $exc->getMessage()]);
        }

        return true;
    }

    public function getSearchQuery($data, $selectStmt = 'SELECT c.*'): array
    {
        $sql = $selectStmt;
        $sql .= ' FROM client as c left join client_group as cg on c.client_group_id = cg.id';

        $search = (isset($data['search']) && !empty($data['search'])) ? $data['search'] : null;
        $client_id = (isset($data['client_id']) && !empty($data['client_id'])) ? $data['client_id'] : null;
        $group_id = (isset($data['group_id']) && !empty($data['group_id'])) ? $data['group_id'] : null;
        $id = (isset($data['id']) && !empty($data['id'])) ? $data['id'] : null;
        $status = (isset($data['status']) && !empty($data['status'])) ? $data['status'] : null;
        $name = (isset($data['name']) && !empty($data['name'])) ? $data['name'] : null;
        $company = (isset($data['company']) && !empty($data['company'])) ? $data['company'] : null;
        $email = (isset($data['email']) && !empty($data['email'])) ? $data['email'] : null;
        $created_at = (isset($data['created_at']) && !empty($data['created_at'])) ? $data['created_at'] : null;
        $date_from = (isset($data['date_from']) && !empty($data['date_from'])) ? $data['date_from'] : null;
        $date_to = (isset($data['date_to']) && !empty($data['date_to'])) ? $data['date_to'] : null;

        $where = [];
        $params = [];
        if ($id) {
            $where[] = '(c.id = :client_id OR c.aid = :alt_client_id)';
            $params[':client_id'] = $id;
            $params[':alt_client_id'] = $id;
        }

        if ($name) {
            $where[] = '(c.first_name LIKE :first_name or c.last_name LIKE :last_name )';
            $name = '%' . $name . '%';
            $params[':first_name'] = $name;
            $params[':last_name'] = $name;
        }

        if ($email) {
            $where[] = 'c.email LIKE :email';
            $params[':email'] = '%' . $email . '%';
        }

        if ($company) {
            $where[] = 'c.company LIKE :company';
            $params[':company'] = '%' . $company . '%';
        }

        if ($status) {
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }

        if ($group_id) {
            $where[] = 'c.client_group_id = :group_id';
            $params[':group_id'] = $group_id;
        }

        if ($created_at) {
            $where[] = "DATE_FORMAT(c.created_at, '%Y-%m-%d') = :created_at";
            $params[':created_at'] = date('Y-m-d', strtotime((string) $created_at));
        }

        if ($date_from) {
            $where[] = 'UNIX_TIMESTAMP(c.created_at) >= :date_from';
            $params[':date_from'] = strtotime((string) $date_from);
        }

        if ($date_to) {
            $where[] = 'UNIX_TIMESTAMP(c.created_at) <= :date_to';
            $params[':date_to'] = strtotime((string) $date_to);
        }

        // smartSearch
        if ($search) {
            if (is_numeric($search)) {
                $where[] = '(c.id = :cid OR c.aid = :caid)';
                $params[':cid'] = $search;
                $params[':caid'] = $search;
            } else {
                $where[] = "(c.company LIKE :s_company OR c.first_name LIKE :s_first_name OR c.last_name LIKE :s_last_name OR c.email LIKE :s_email OR CONCAT(c.first_name,  ' ', c.last_name ) LIKE  :full_name)";
                $search = '%' . $search . '%';
                $params[':s_company'] = $search;
                $params[':s_first_name'] = $search;
                $params[':s_last_name'] = $search;
                $params[':s_email'] = $search;
                $params[':full_name'] = $search;
            }
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY c.created_at desc';

        return [$sql, $params];
    }

    public function getPairs($data)
    {
        $limit = $data['per_page'] ?? 30;
        if (!is_numeric($limit) || $limit < 1) {
            throw new InformationException('Invalid per page number');
        }

        return $this->clientRepository->getIdNamePairs($data, (int) $limit);
    }

    public function toSessionArray(Client|\Model_Client $model): array
    {
        $id = $this->getClientId($model);

        return [
            'id' => $id,
            'email' => $model instanceof Client ? $model->getEmail() : $model->email,
            'name' => $model->getFullName(),
            'role' => $model instanceof Client ? $model->getRole() : $model->role,
        ];
    }

    public function emailAlreadyRegistered($new_email, Client|\Model_Client|null $model = null): bool
    {
        if ($model instanceof Client && $model->getEmail() == $new_email) {
            return false;
        }

        if ($model instanceof \Model_Client && $model->email == $new_email) {
            return false;
        }

        return $this->clientRepository->findOneByEmail($new_email) instanceof Client;
    }

    public function canChangeCurrency(Client|\Model_Client $model, $currency = null): bool
    {
        $modelCurrency = $model instanceof Client ? $model->getCurrency() : $model->currency;
        if (!$modelCurrency) {
            return true;
        }

        if ($modelCurrency == $currency) {
            return false;
        }

        $clientId = $this->getClientId($model);
        if ($this->di['dbal']->fetchOne('SELECT 1 FROM invoice WHERE client_id = :client_id LIMIT 1', ['client_id' => $clientId])) {
            throw new InformationException('Currency cannot be changed. Client already has invoices issued.');
        }

        if ($this->di['dbal']->fetchOne('SELECT 1 FROM client_order WHERE client_id = :client_id LIMIT 1', ['client_id' => $clientId])) {
            throw new InformationException('Currency cannot be changed. Client already has orders.');
        }

        return true;
    }

    public function addFunds(Client|\Model_Client $client, $amount, $description, array $data = []): bool
    {
        $currency = $client instanceof Client ? $client->getCurrency() : $client->currency;
        if (!$currency) {
            throw new InformationException('You must define the client\'s currency before adding funds.');
        }

        if (!is_numeric($amount)) {
            throw new InformationException('Funds amount is invalid');
        }

        if (empty($description)) {
            throw new InformationException('Funds description is invalid');
        }

        $credit = new ClientBalance();
        $credit->setClientId($this->getClientId($client));
        $credit->setType($data['type'] ?? 'gift');
        $credit->setRelId(isset($data['rel_id']) ? (string) $data['rel_id'] : null);
        $credit->setDescription($description);
        $credit->setAmount((string) $amount);

        $this->di['em']->persist($credit);
        $this->di['em']->flush();

        return true;
    }

    public function getExpiredPasswordReminders()
    {
        $expireAfterHours = 2;
        $cutoff = new \DateTime("-{$expireAfterHours} hours");

        return $this->clientPasswordResetRepository->findExpiredBefore($cutoff);
    }

    public function getHistorySearchQuery($data): array
    {
        $q = 'SELECT ach.*, c.first_name, c.last_name, c.email
              FROM activity_client_history as ach
                LEFT JOIN client as c on ach.client_id = c.id ';

        $id = $data['id'] ?? null;
        $search = $data['search'] ?? null;
        $client_id = $data['client_id'] ?? null;
        $ip = $data['ip'] ?? null;
        $date_from = $data['date_from'] ?? null;
        $date_to = $data['date_to'] ?? null;

        $where = [];
        $params = [];

        if ($id !== null && $id !== '') {
            $where[] = 'ach.id = :event_id';
            $params[':event_id'] = (int) $id;
        }

        if ($search) {
            $where[] = '(c.first_name LIKE :first_name OR c.last_name LIKE :last_name OR c.email LIKE :email OR c.id LIKE :id)';
            $params[':first_name'] = '%' . $search . '%';
            $params[':last_name'] = '%' . $search . '%';
            $params[':email'] = '%' . $search . '%';
            $params[':id'] = $search;
        }

        if ($client_id) {
            $where[] = 'ach.client_id = :client_id';
            $params[':client_id'] = $client_id;
        }

        if ($ip !== null && $ip !== '') {
            $where[] = 'ach.ip LIKE :ip';
            $params[':ip'] = '%' . $ip . '%';
        }

        if ($date_from !== null && $date_from !== '') {
            $where[] = 'ach.created_at >= :date_from';
            $params[':date_from'] = date('Y-m-d 00:00:00', strtotime((string) $date_from));
        }

        if ($date_to !== null && $date_to !== '') {
            $where[] = 'ach.created_at <= :date_to';
            $params[':date_to'] = date('Y-m-d 23:59:59', strtotime((string) $date_to));
        }

        if (!empty($where)) {
            $q .= ' WHERE ' . implode(' AND ', $where);
        }

        $q .= ' ORDER BY ach.id desc';

        return [$q, $params];
    }

    public function counter(): array
    {
        $counts = $this->clientRepository->getStatusCounts();

        return [
            'total' => array_sum($counts),
            Client::ACTIVE => $counts['active'],
            Client::SUSPENDED => $counts['suspended'],
            Client::CANCELED => $counts['canceled'],
        ];
    }

    public function getGroupPairs()
    {
        return $this->clientGroupRepository->getIdTitlePairs();
    }

    public function clientAlreadyExists($email): bool
    {
        return $this->clientRepository->findOneByEmail($email) instanceof Client;
    }

    public function getByLoginDetails($email, $password)
    {
        return $this->clientRepository->findOneBy(['email' => $email, 'pass' => $password, 'status' => Client::ACTIVE]);
    }

    public function toApiArray(Client|\Model_Client $model, $deep = false, $identity = null, bool $includeSensitive = false): array
    {
        if ($model instanceof Client) {
            return $this->toClientApiArray($model, null, $deep, $identity, $includeSensitive);
        }

        $client = $this->clientRepository->find((int) $model->id);
        if ($client instanceof Client) {
            return $this->toClientApiArray($client, $model, $deep, $identity, $includeSensitive);
        }

        return $this->toLegacyClientApiArray($model, $deep, $identity, $includeSensitive);
    }

    public function toClientApiArray(Client $client, Client|\Model_Client|null $model = null, bool $deep = false, $identity = null, bool $includeSensitive = false): array
    {
        $isAdmin = $this->isAdminIdentity($identity);
        $isSelf = $this->isClientIdentity($identity) && $this->getIdentityId($identity) === $client->getId();
        $details = $client->toApiArray($isAdmin ? $identity : null);

        if ($isAdmin || $isSelf) {
            $details['billing_email'] = $model instanceof Client ? $model->getBillingEmail() : $client->getBillingEmail();
        }

        if ($deep) {
            $details['balance'] = $this->getClientBalanceFromEntity($client);
        }

        if ($isAdmin) {
            $details['group'] = null;

            if ($client->getClientGroupId()) {
                $group = $this->clientGroupRepository->find($client->getClientGroupId());
                if ($group instanceof ClientGroup) {
                    $details['group'] = $group->getTitle();
                    $details['client_group'] = [
                        'id' => $group->getId(),
                        'title' => $group->getTitle(),
                    ];
                }
            }

            if ($includeSensitive) {
                $details['api_token'] = $client->getApiToken();
            }
        } else {
            $config = $this->di['mod_config']('client');
            for ($i = 1; $i <= 20; ++$i) {
                $field = 'custom_' . $i;
                $value = $client->{'getCustom' . $i}();
                if (($config['custom_fields'][$field]['active'] ?? false) && !empty($value)) {
                    $details[$field] = $value;
                }
            }
        }

        return $details;
    }

    public function getClientBalance(Client|\Model_Client $c): float
    {
        return $this->clientBalanceRepository->getClientBalanceSum($this->getClientId($c));
    }

    private function getClientBalanceFromEntity(Client $client): float
    {
        return $this->clientBalanceRepository->getClientBalanceSum((int) $client->getId());
    }

    private function getClientId(Client|\Model_Client $client): int
    {
        return (int) ($client instanceof Client ? $client->getId() : $client->id);
    }

    private function getIdentityId(mixed $identity): ?int
    {
        if ($identity instanceof Client || $identity instanceof Admin) {
            return $identity->getId();
        }

        if ($identity instanceof \Model_Client || $identity instanceof \Model_Admin) {
            return (int) $identity->id;
        }

        return null;
    }

    private function isAdminIdentity(mixed $identity): bool
    {
        return $identity instanceof Admin || $identity instanceof \Model_Admin;
    }

    private function isClientIdentity(mixed $identity): bool
    {
        return $identity instanceof Client || $identity instanceof \Model_Client;
    }

    private function toLegacyClientApiArray(\Model_Client $client, bool $deep, mixed $identity, bool $includeSensitive): array
    {
        $details = [
            'id' => $client->id,
            'email' => $client->email,
            'email_approved' => $client->email_approved,
            'type' => $client->type,
            'company' => $client->company,
            'company_vat' => $client->company_vat,
            'company_number' => $client->company_number,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'gender' => $client->gender,
            'birthday' => $client->birthday,
            'phone_cc' => $client->phone_cc,
            'phone' => $client->phone,
            'address_1' => $client->address_1,
            'address_2' => $client->address_2,
            'city' => $client->city,
            'state' => $client->state,
            'postcode' => $client->postcode,
            'country' => $client->country,
            'currency' => $client->currency,
            'lang' => $client->lang,
            'timezone' => $client->timezone,
        ];

        $isAdmin = $this->isAdminIdentity($identity);
        $isSelf = $this->isClientIdentity($identity) && $this->getIdentityId($identity) === (int) $client->id;
        if ($isAdmin || $isSelf) {
            $details['billing_email'] = $client->billing_email;
        }

        if ($deep) {
            $details['balance'] = $this->getClientBalance($client);
        }

        if ($isAdmin) {
            for ($i = 1; $i <= 20; ++$i) {
                $field = 'custom_' . $i;
                $details[$field] = $client->{$field};
            }

            $group = $this->di['db']->load('ClientGroup', $client->client_group_id);
            $details += [
                'aid' => $client->aid,
                'group_id' => $client->client_group_id,
                'auth_type' => $client->auth_type,
                'notes' => $client->notes,
                'status' => $client->status,
                'tax_exempt' => $client->tax_exempt,
                'ip' => $client->ip,
                'group' => $group ? $group->title : null,
                'created_at' => $client->created_at,
                'updated_at' => $client->updated_at,
            ];

            if ($includeSensitive) {
                $details['api_token'] = $client->api_token;
            }
        } else {
            $config = $this->di['mod_config']('client');
            foreach (($config['custom_fields'] ?? []) as $fieldName => $fieldConfig) {
                if (($fieldConfig['active'] ?? false) && !empty($client->{$fieldName})) {
                    $details[$fieldName] = $client->{$fieldName};
                }
            }
        }

        return $details;
    }

    private function getLegacyClient(int $clientId): \Model_Client
    {
        $client = $this->di['db']->getExistingModelById('Client', $clientId);
        if (!$client instanceof \Model_Client) {
            throw new \FOSSBilling\Exception('Client compatibility model not found');
        }

        return $client;
    }

    public function get($data)
    {
        if (!isset($data['id']) && !isset($data['email'])) {
            throw new InformationException('Client ID or email is required');
        }

        $client = null;
        if (isset($data['id'])) {
            $client = $this->clientRepository->find((int) $data['id']);
        }

        if (!$client instanceof Client && isset($data['email'])) {
            $client = $this->clientRepository->findOneByEmail($data['email']);
        }

        if (!$client instanceof Client) {
            throw new InformationException('Client not found');
        }

        return $client;
    }

    public function isClientTaxable(Client|\Model_Client $model): bool
    {
        $systemService = $this->di['mod_service']('system');

        if (!$systemService->getParamValue('tax_enabled', false)) {
            return false;
        }

        $taxExempt = $model instanceof Client ? $model->isTaxExempt() : (bool) $model->tax_exempt;
        if ($taxExempt) {
            return false;
        }

        return true;
    }

    public function createGroup(array $data)
    {
        $group = new ClientGroup();
        $group->setTitle($data['title']);

        $this->di['em']->persist($group);
        $this->di['em']->flush();

        $this->di['logger']->info('Created new client group #%s', $group->getId());

        return (int) $group->getId();
    }

    public function deleteGroup(ClientGroup $model): bool
    {
        $client = $this->clientRepository->findOneBy(['clientGroupId' => $model->getId()]);
        if ($client) {
            throw new \FOSSBilling\Exception('Cannot remove groups with clients');
        }

        $group = $this->clientGroupRepository->find((int) $model->getId());
        if ($group instanceof ClientGroup) {
            $this->di['em']->remove($group);
            $this->di['em']->flush();
        }
        $this->di['logger']->info('Removed client group #%s', $model->getId());

        return true;
    }

    private function createClient(array $data): Client
    {
        $password = $data['password'] ?? $this->di['tools']->generatePassword(32, true);

        $client = new Client();
        $client->setAuthType($data['auth_type'] ?? null);
        $client->setEmail(strtolower(trim((string) ($data['email'] ?? null))));
        $billingEmail = trim((string) ($data['billing_email'] ?? ''));
        $client->setBillingEmail($billingEmail !== '' ? strtolower($billingEmail) : null);
        $client->setFirstName(ucwords((string) ($data['first_name'] ?? null)));
        $client->setPass($this->di['password']->hashIt($password));

        $system = $this->di['mod']('system');
        $systemCfg = $system->getConfig();

        $phoneCC = $data['phone_cc'] ?? null;
        if (!empty($phoneCC)) {
            $client->setPhoneCc((string) Tools::validatePhoneCC($phoneCC));
        }

        $phone = $data['phone'] ?? null;
        if (!empty($phone) && is_string($phone)) {
            $client->setPhone(Tools::validatePhoneNumber($phone));
        }

        $client->setAid($data['aid'] ?? null);
        $client->setLastName($data['last_name'] ?? null);
        $client->setClientGroupId(!empty($data['group_id']) ? (int) $data['group_id'] : null);
        $client->setStatus($data['status'] ?? Client::ACTIVE);
        $client->setGender($data['gender'] ?? null);
        $birthday = $data['birthday'] ?? null;
        if ($birthday) {
            $client->setBirthday(new \DateTime($birthday));
        }
        $client->setCompany($data['company'] ?? null);
        $client->setCompanyVat($data['company_vat'] ?? null);
        $client->setCompanyNumber($data['company_number'] ?? null);
        $client->setType($data['type'] ?? null);
        $client->setAddress1($data['address_1'] ?? null);
        $client->setAddress2($data['address_2'] ?? null);
        $client->setCity($data['city'] ?? null);
        $client->setState($data['state'] ?? null);
        $client->setPostcode($data['postcode'] ?? null);
        $country = !empty($data['country']) ? $data['country'] : (!empty($systemCfg['default_country']) ? $systemCfg['default_country'] : null);
        if ($country !== null && !Countries::exists($country)) {
            throw new InformationException('Invalid country code: :code', [':code' => $country]);
        }
        $client->setCountry($country);
        $client->setNotes($data['notes'] ?? null);
        $client->setLang($data['lang'] ?? null);
        if ($client->getLang() !== null && $client->getLang() !== '' && !Locales::exists($client->getLang())) {
            throw new InformationException('Invalid locale code: :code', [':code' => $client->getLang()]);
        }
        $client->setTimezone(i18n::validateTimezone($data['timezone'] ?? null));
        $client->setCurrency($data['currency'] ?? null);

        $client->setCustom1($data['custom_1'] ?? null);
        $client->setCustom2($data['custom_2'] ?? null);
        $client->setCustom3($data['custom_3'] ?? null);
        $client->setCustom4($data['custom_4'] ?? null);
        $client->setCustom5($data['custom_5'] ?? null);
        $client->setCustom6($data['custom_6'] ?? null);
        $client->setCustom7($data['custom_7'] ?? null);
        $client->setCustom8($data['custom_8'] ?? null);
        $client->setCustom9($data['custom_9'] ?? null);
        $client->setCustom10($data['custom_10'] ?? null);
        $client->setCustom11($data['custom_11'] ?? null);
        $client->setCustom12($data['custom_12'] ?? null);
        $client->setCustom13($data['custom_13'] ?? null);
        $client->setCustom14($data['custom_14'] ?? null);
        $client->setCustom15($data['custom_15'] ?? null);
        $client->setCustom16($data['custom_16'] ?? null);
        $client->setCustom17($data['custom_17'] ?? null);
        $client->setCustom18($data['custom_18'] ?? null);
        $client->setCustom19($data['custom_19'] ?? null);
        $client->setCustom20($data['custom_20'] ?? null);

        $client->setIp($data['ip'] ?? null);

        $createdAt = $data['created_at'] ?? null;
        if (!empty($createdAt)) {
            $client->setCreatedAt(new \DateTime((string) $createdAt));
        }

        $this->di['em']->persist($client);
        $this->di['em']->flush();

        return $client;
    }

    public function adminCreateClient(array $data)
    {
        $eventParams = $data;
        unset($eventParams['password'], $eventParams['password_confirm']);
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminCreateClient', 'params' => $eventParams]);
        $client = $this->createClient($data);
        if (Tools::normalizeBoolean($data['send_welcome_email'] ?? true, true)) {
            $this->sendAdminCreatedWelcomeEmailForClient($client);
        }
        $this->di['events_manager']->fire(['event' => 'onAfterAdminCreateClient', 'params' => ['id' => $client->getId()]]);
        $this->di['logger']->info('Created new client #%s', $client->getId());

        return (int) $client->getId();
    }

    public function guestCreateClient(array $data)
    {
        $event_params = $data;
        $event_params['ip'] = $this->di['request']->getClientIp();
        unset($event_params['password'], $event_params['password_confirm']);
        $this->di['events_manager']->fire(['event' => 'onBeforeClientSignUp', 'params' => $event_params]);

        $allowedFields = [
            'email', 'first_name', 'last_name', 'password',
            'phone', 'phone_cc', 'gender', 'birthday',
            'company', 'company_vat', 'company_number', 'type',
            'address_1', 'address_2', 'city', 'state', 'postcode', 'country',
            'lang', 'timezone',
            'custom_1', 'custom_2', 'custom_3', 'custom_4', 'custom_5',
            'custom_6', 'custom_7', 'custom_8', 'custom_9', 'custom_10',
            'custom_11', 'custom_12', 'custom_13', 'custom_14', 'custom_15',
            'custom_16', 'custom_17', 'custom_18', 'custom_19', 'custom_20',
        ];

        $safeData = [
            'ip' => $this->di['request']->getClientIp(),
            'status' => Client::ACTIVE,
        ];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $safeData[$field] = $data[$field];
            }
        }

        $client = $this->createClient($safeData);

        $event_params = [
            'id' => $client->getId(),
            'email' => $client->getEmail(),
            'first_name' => $client->getFirstName(),
            'last_name' => $client->getLastName(),
            'ip' => $safeData['ip'],
        ];
        $this->di['events_manager']->fire(['event' => 'onAfterClientSignUp', 'params' => $event_params]);
        $this->di['logger']->info('Client #%s signed up', $client->getId());

        return $client;
    }

    public function createPasswordResetRequestForClient(Client|\Model_Client $client): string
    {
        $clientId = $this->getClientId($client);
        $clientIp = $client instanceof Client ? $client->getIp() : $client->ip;

        $existingReset = $this->clientPasswordResetRepository->findOneBy(['clientId' => $clientId]);
        if ($existingReset instanceof ClientPasswordReset) {
            $this->di['em']->remove($existingReset);
            $this->di['em']->flush();
        }

        $requestIp = null;
        if (isset($this->di['request']) && is_object($this->di['request']) && method_exists($this->di['request'], 'getClientIp')) {
            $requestIp = $this->di['request']->getClientIp();
        }

        $hash = hash('sha256', random_bytes(32));
        $reset = new ClientPasswordReset();
        $reset->setClientId($clientId);
        $reset->setIp($requestIp ?? $clientIp);
        $reset->setHash($hash);

        $this->di['em']->persist($reset);
        $this->di['em']->flush();

        return $hash;
    }

    public function sendPasswordResetRequestEmailForClient(Client|\Model_Client $client, string $hash, bool $sendNow = true): void
    {
        $clientId = $this->getClientId($client);

        $email = [
            'to_client' => $clientId,
            'code' => 'mod_client_password_reset_request',
            'hash' => $hash,
            'send_now' => $sendNow,
        ];

        $emailService = $this->di['mod_service']('email');
        $emailService->sendTemplate($email);
    }

    public function sendAdminCreatedWelcomeEmailForClient(Client|\Model_Client $client): void
    {
        try {
            $clientId = $this->getClientId($client);

            $email = [];
            $email['to_client'] = $clientId;
            $email['code'] = 'mod_client_signup_admin';
            $email['hash'] = $this->createPasswordResetRequestForClient($client);
            $email['send_now'] = true;
            $email['require_email_confirmation'] = false;

            $config = $this->di['mod_config']('client');
            if (isset($config['require_email_confirmation']) && $config['require_email_confirmation']) {
                $email['require_email_confirmation'] = true;
                $email['email_confirmation_link'] = $this->generateEmailConfirmationLink($clientId);
            }

            $emailService = $this->di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $this->di['logger']->setChannel('email')->error('Failed to send client welcome email', ['exception' => $exc->getMessage()]);
        }
    }

    public function remove(Client|\Model_Client $model): void
    {
        $clientId = $this->getClientId($model);
        $legacyClient = $model instanceof \Model_Client ? $model : $this->getLegacyClient($clientId);
        $entityManager = $this->di['em'];
        $connection = $entityManager->getConnection();

        $entityManager->beginTransaction();

        try {
            $service = $this->di['mod_service']('Order');
            $service->rmByClient($legacyClient);
            $service = $this->di['mod_service']('Invoice');
            $service->rmByClient($legacyClient);
            $service = $this->di['mod_service']('Support');
            $service->rmByClient($legacyClient);
            $service = $this->di['mod_service']('Client', 'Balance');
            $service->rmByClient($model);

            $connection->executeStatement('DELETE FROM activity_client_history WHERE client_id = :id', ['id' => $clientId]);

            $service = $this->di['mod_service']('Email');
            $service->rmByClient($legacyClient);
            $service = $this->di['mod_service']('Activity');
            $service->rmByClient($model);

            $resetRecords = $this->clientPasswordResetRepository->findBy(['clientId' => $clientId]);
            foreach ($resetRecords as $resetRecord) {
                $entityManager->remove($resetRecord);
            }

            $query = $connection->createQueryBuilder();
            $query
                ->delete('extension_meta')
                ->where('client_id = :id')
                ->setParameter('id', $clientId);
            $query->executeStatement();

            $client = $model instanceof Client ? $model : $this->clientRepository->find($clientId);
            if ($client instanceof Client) {
                $entityManager->remove($client);
            }

            $entityManager->flush();
            $entityManager->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $entityManager->rollback();
            }

            throw $exception;
        }
    }

    public function authorizeClient($email, $plainTextPassword)
    {
        // The shared authorization service still reads the legacy bean fields directly.
        // Keep that boundary until the identity/authentication slice migrates as well.
        $legacyClient = $this->di['db']->findOne('Client', 'email = ? AND status = ?', [$email, Client::ACTIVE]);
        $authUser = $legacyClient;
        if (!$authUser) {
            $candidate = $this->clientRepository->findOneBy(['email' => $email, 'status' => Client::ACTIVE]);
            $authUser = $candidate instanceof Client ? $candidate : null;
        }

        $authorized = $this->di['auth']->authorizeUser($authUser, $plainTextPassword);
        if (!$authorized) {
            return null;
        }

        if ($authorized instanceof Client) {
            return $authorized;
        }

        $authorizedId = $authorized->id;
        $client = $this->clientRepository->find((int) $authorizedId);

        return $client instanceof Client ? $client : null;
    }

    public function sendEmailConfirmationForClient(Client|\Model_Client $client): void
    {
        $clientId = $this->getClientId($client);

        try {
            $email = [];
            $email['to_client'] = $clientId;
            $email['code'] = 'mod_client_confirm';
            $email['require_email_confirmation'] = true;
            $email['email_confirmation_link'] = $this->generateEmailConfirmationLink($clientId);
            $email['send_now'] = true;

            $emailService = $this->di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $this->di['logger']->setChannel('email')->error('Failed to send email confirmation email', ['exception' => $exc->getMessage()]);
        }
    }

    public function canChangeEmail(Client|\Model_Client $client, $email): bool
    {
        $config = $this->di['mod_config']('client');

        if (
            ($client instanceof Client ? $client->getEmail() : $client->email) != $email
            && isset($config['disable_change_email'])
            && $config['disable_change_email']
        ) {
            throw new InformationException('Email address cannot be changed');
        }

        return true;
    }

    public function checkExtraRequiredFields(array $checkArr): void
    {
        $config = $this->di['mod_config']('client');
        $required = $config['required'] ?? [];
        foreach ($required as $field) {
            if (!isset($checkArr[$field]) || empty($checkArr[$field])) {
                $name = ucwords(str_replace('_', ' ', $field));

                throw new InformationException('Field :field cannot be empty', [':field' => $name]);
            }
        }
    }

    public function checkCustomFields(array $checkArr): void
    {
        $config = $this->di['mod_config']('client');
        $customFields = $config['custom_fields'] ?? [];
        foreach ($customFields as $cFieldName => $cField) {
            $active = isset($cField['active']) && $cField['active'];
            $required = isset($cField['required']) && $cField['required'];
            if ($active && $required) {
                if (!isset($checkArr[$cFieldName]) || empty($checkArr[$cFieldName])) {
                    $name = isset($cField['title']) && !empty($cField['title']) ? $cField['title'] : ucwords(str_replace('_', ' ', $cFieldName));

                    throw new InformationException('Field :field cannot be empty', [':field' => $name]);
                }
            }
        }
    }

    public function resolveDocumentNumber(Client|\Model_Client $client): ?string
    {
        $config = $this->di['mod_config']('client');
        $customFields = $config['custom_fields'] ?? [];

        $keywords = ['passport', 'document', 'identity', 'id number'];

        foreach (range(1, 20) as $i) {
            $fieldName = 'custom_' . $i;
            $fieldConfig = $customFields[$fieldName] ?? null;
            if (!is_array($fieldConfig) || !($fieldConfig['active'] ?? false)) {
                continue;
            }
            $title = strtolower((string) ($fieldConfig['title'] ?? ''));
            if ($title === '' || !array_filter($keywords, fn ($k): bool => str_contains($title, (string) $k))) {
                continue;
            }
            $value = $client instanceof Client
                ? $client->{'getCustom' . $i}()
                : ($client->{$fieldName} ?? null);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    public function exportCSV(array $headers): Response
    {
        if ($headers) {
            // Prevent the password / salt columns from being exported
            if (isset($headers['pass'])) {
                unset($headers['pass']);
            }
            if (isset($headers['salt'])) {
                unset($headers['salt']);
            }
        } else {
            $headers = ['id', 'email', 'status', 'first_name', 'last_name', 'phone_cc', 'phone', 'company', 'company_vat', 'company_number', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'currency'];
        }

        return $this->di['csv_response_factory']->create('client', 'clients.csv', $headers);
    }

    /**
     * Confirm password reset action.
     *
     * @return bool|int
     *
     * @throws InformationException
     */
    public function password_reset_valid($data)
    {
        $required = [
            'hash' => 'Hash required',
        ];
        $this->di['events_manager']->fire(['event' => 'onBeforePasswordResetClient']);
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $reset = $this->clientPasswordResetRepository->findOneByHash($data['hash']);
        if (!$reset instanceof ClientPasswordReset) {
            throw new InformationException('The link has expired or you have already reset your password.');
        }

        $client = $reset->getClientId() !== null ? $this->clientRepository->find($reset->getClientId()) : null;
        if (!$client instanceof Client) {
            throw new InformationException('The link has expired or you have already reset your password.');
        }

        if (strtotime((string) $reset->getCreatedAt()?->format('Y-m-d H:i:s')) - time() + 900 < 0) {
            return false;
        }

        return $client->getId();
    }

    /*
     * Prunes the `client_password_reset` table of reset requests older than 15 minutes
     *
     * @return void
     */
    public static function onBeforeAdminCronRun(\Box_Event $event): void
    {
        $di = $event->getDi();

        try {
            $cutoff = new \DateTime('-900 seconds');
            $di['em']->getRepository(ClientPasswordReset::class)
                ->createQueryBuilder('r')
                ->delete()
                ->where('r.createdAt < :cutoff')
                ->setParameter('cutoff', $cutoff)
                ->getQuery()
                ->execute();
        } catch (\Exception $e) {
            if (!\FOSSBilling\Environment::isTesting()) {
                error_log($e->getMessage());
            }
        }
    }
}
