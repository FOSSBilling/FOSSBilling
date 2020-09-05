<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 *Staff management 
 */
namespace Box\Mod\Staff\Api;
class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of staff members
     * 
     * @return array 
     */
    public function get_list($data)
    {
        $data['no_cron'] = true;
        list($sql, $params) = $this->getService()->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach($pager['list'] as $key => $item){
            $staff = $this->di['db']->getExistingModelById('Admin', $item['id'], 'Admin is not found');
            $pager['list'][$key] = $this->getService()->toModel_AdminApiiArray($staff);
        }

        return $pager;

    }
    
    /**
     * Get staff member by id
     * 
     * @param int $id - staff member ID
     * @return array
     * @throws Exception 
     */
    public function get($data)
    {
        $required = array(
            'id' => 'ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');
        return $this->getService()->toModel_AdminApiiArray($model);
    }
    
    /**
     * Update staff member
     * 
     * @param int $id - staff member ID
     * 
     * @optional string $email - new email
     * @optional string $name - new name
     * @optional string $status - new status
     * @optional string $signature - new signature
     * @optional int $admin_group_id - new group id 
     * 
     * @return boolean
     * @throws Exception 
     */
    public function update($data)
    {
        $required = array(
            'id' => 'ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');

        return $this->getService()->update($model, $data);
    }

    /**
     * Completely delete staff member. Removes all related acitivity from logs
     * 
     * @param int $id - staff member ID
     * @return boolean
     * @throws Exception 
     */
    public function delete($data)
    {
        $required = array(
            'id' => 'ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');
        
        return $this->getService()->delete($model);
    }

    /**
     * Change staff member password
     * 
     * @param int $id - staff member ID
     * @param string $password - new staff member password
     * @param string $password_confirm - repeat new staff member password 
     * @return boolean
     * @throws Exception 
     */
    public function change_password($data)
    {
        $required = array(
            'id' => 'ID is missing',
            'password' => 'Password required',
            'password_confirm' => 'Password confirmation required',
        );
        $validator = $this->di['validator'];
        $validator->checkRequiredParamsForArray($required, $data);
        
        if($data['password'] != $data['password_confirm']) {
            throw new \Box_Exception('Passwords do not match');
        }


        $validator->isPasswordStrong($data['password']);

        $model = $this->di['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');
        return $this->getService()->changePassword($model, $data['password']);
    }
    
    /**
     * Create new staff member
     * 
     * @param string $email - email of new staff member
     * @param string $password - password of new staff member
     * @param string $name - name of new staff member
     * @param string $admin_group_id - admin group id of new staff member
     * 
     * @optional string $signature - signature of new staff member
     * 
     * @return int - ID of newly created staff member
     * @throws Exception 
     */
    public function create($data)
    {
        $required = array(
            'email' => 'Email param is missing',
            'password' => 'Password param is missing',
            'name' => 'Name param is missing',
            'admin_group_id' => 'Group id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);


        return $this->getService()->create($data);
    }

    /**
     * Return staff member permissions
     * 
     * @param int $id - staff member id
     * 
     * @return array
     */
    public function permissions_get($data)
    {
        $required = array(
            'id' => 'ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');
        
        return $this->getService()->getPermissions($model->id);
    }
    
    /**
     * Update staff member permissions
     * 
     * @param int $id - staff member id
     * @param array $permissions - staff member permissions
     * 
     * @return bool
     */
    public function permissions_update($data)
    {
        $required = array(
            'id' => 'ID is missing',
            'permissions' => 'Permissions parameter missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');

        $this->getService()->setPermissions($model->id, $data['permissions']);
        
        $this->di['logger']->info('Changed staff member %s permisions', $model->id);
        return true;
    }

    /**
     * Return pairs of staff member groups
     * 
     * @return type 
     */
    public function group_get_pairs($data)
    {
        return $this->getService()->getAdminGroupPair();
    }

    /**
     * Return paginate list of staff members groups
     * @return array 
     */
    public function group_get_list($data)
    {
        list($sql, $params) = $this->getService()->getAdminGroupSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $model               = $this->di['db']->getExistingModelById('AdminGroup', $item['id'], 'Post not found');
            $pager['list'][$key] = $this->getService()->toAdminGroupApiArray($model, false, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Create new staff members group
     * 
     * @param string $name - name of staff members group
     * 
     * @return int - new staff group ID
     * @throws Exception 
     */
    public function group_create($data)
    {
        $required = array(
            'name' => 'Staff group is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        return $this->getService()->createGroup($data['name']);
    }

    /**
     * Return staff group details
     * 
     * @param int $id - group id
     * @return array - group details
     * @throws Exception 
     */
    public function group_get($data)
    {

        $required = array(
            'id' => 'Group id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('AdminGroup', $data['id'], 'Group not found');

        return $this->getService()->toAdminGroupApiArray($model, true, $this->getIdentity());
    }

    /**
     * Remove staff group
     * 
     * @param int $id - group id
     * @return boolean
     * @throws Exception 
     */
    public function group_delete($data)
    {
        $required = array(
            'id' => 'Group id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('AdminGroup', $data['id'], 'Group not found');
        return $this->getService()->deleteGroup($model);
    }

    /**
     * Update staff group
     * 
     * @param int $id - group id
     * 
     * @optional int $name - new group name
     * 
     * @return boolean
     * @throws Exception 
     */
    public function group_update($data)
    {
        $required = array(
            'id' => 'Group id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('AdminGroup', $data['id'], 'Group not found');

        return $this->getService()->updateGroup($model, $data);
    }

    /**
     * Get paginated list of staff logins history
     * 
     * @return array
     */
    public function login_history_get_list($data)
    {
        list($sql, $params) = $this->getService()->getActivityAdminHistorySearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $activity = $this->di['db']->getExistingModelById('ActivityAdminHistory', $item['id'], sprintf('Staff activity item #%s not found', $item['id']));
            if ($activity) {
                $pager['list'][$key] = $this->getService()->toActivityAdminHistoryApiArray($activity);
            }
        }

        return $pager;
    }

    /**
     * Get details of login history event
     * 
     * @param int $id - event id
     * @return array
     * @throws ErrorException 
     */
    public function login_history_get($data)
    {
        $required = array(
            'id' => 'Id not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ActivityAdminHistory', $data['id'], 'Event not found');
        
        return $this->getService()->toActivityAdminHistoryApiArray($model);
    }
    
    /**
     * Delete login history event
     * 
     * @param int $id - event id
     * @return boolean
     * @throws ErrorException 
     */
    public function login_history_delete($data)
    {
        $required = array(
            'id' => 'Id not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $model = $this->di['db']->getExistingModelById('ActivityAdminHistory', $data['id'], 'Event not found');

        return $this->getService()->deleteLoginHistory($model);
    }
    
    /**
     * Returns currently loggen in staff member information
     *
     * @return array
     * @deprecated moved to profile module
     * @codeCoverageIgnore
     * @example
     * <code class="response">
     * Array
	 * (
     * 		[id] => 1
     *		[role] => staff
     *		[admin_group_id] => 1
     *		[email] => demo@boxbilling.com
     *		[pass] => 89e495e7941cf9e40e6980d14a16bf023ccd4c91
     *		[name] => Demo Administrator
     *		[signature] => Sincerely Yours, Demo Administrator
     * 		[status] => active
     *		[api_token] => 29baba87f1c120f1b7fc6b0139167003
     *		[created_at] => 1310024416
     *		[updated_at] => 1310024416
	 * )
	 * </code>
     */
    public function profile_get()
    {
        return $this->di['db']->toArray($this->getIdentity());
    }

    /**
     * Clear session data and logout from system
     * @deprecated moved to profile module
     * @codeCoverageIgnore
     * @return boolean 
     */
    public function profile_logout()
    {
        if($_COOKIE) { // testing env fix
            $this->di['cookie']->set('BOXADMR', "", time() - 3600, '/');
        }
        $this->di['session']->delete('admin');
        $this->di['logger']->info('Logged out');
        return true;
    }

    /**
     * Update currently logged in staff member details
     * 
     * @optional string $email - new email
     * @optional string $name - new name
     * @optional string $signature - new signature
     * @deprecated moved to profile module
     * @return boolean
     * @throws Exception
     * @codeCoverageIgnore
     */
    public function profile_update($data)
    {
        $event_params = $data;
        $event_params['id'] = $this->getIdentity()->id;
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminStaffProfileUpdate', 'params'=>$event_params));
        
        $admin = $this->getIdentity();

        $admin->email = $this->di['array_get']($data, 'email', $admin->email);
        $admin->name = $this->di['array_get']($data, 'name', $admin->name);
        $admin->signature = $this->di['array_get']($data, 'signature', $admin->signature);
        $admin->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($admin);

        $event_params = array();
        $event_params['id'] = $this->getIdentity()->id;
        $this->di['events_manager']->fire(array('event'=>'onAfterAdminStaffProfileUpdate', 'params'=>$event_params));
        
        $this->di['logger']->info('Updated profile');
        return true;
    }

    /**
     * Generates new API token for currently logged in staff member
     * @deprecated moved to profile module
     * @return boolean
     *  @codeCoverageIgnore
     */
    public function profile_generate_api_key($data)
    {
        $admin = $this->getIdentity();
        
        $event_params = array();
        $event_params['id'] = $this->getIdentity()->id;
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminStaffApiKeyChange', 'params'=>$event_params));
        
        $admin->api_token = $this->di['tools']->generatePassword(32);
        $admin->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($admin);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminStaffApiKeyChange', 'params'=>$event_params));
        
        $this->di['logger']->info('Generated new API key');
        return true;
    }

    /**
     * Change password for currently logged in staff member
     * 
     * @param string $password - new password
     * @param string $password_confirm - repeat new password
     * @return boolean
     * @deprecated moved to profile module
     * @throws Exception
     *  @codeCoverageIgnore
     */
    public function profile_change_password($data)
    {
        $required = array(
            'password'         => 'Password required',
            'password_confirm' => 'Password confirmation required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if($data['password'] != $data['password_confirm']) {
            throw new \Box_Exception('Passwords do not match');
        }

        $this->di['validator']->isPasswordStrong($data['password']);
        
        $event_params = $data;
        $event_params['id'] = $this->getIdentity()->id;
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminStaffProfilePasswordChange', 'params'=>$event_params));
        
        $admin = $this->getIdentity();

        $admin->pass = $data['password'];
        $admin->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($admin);

        $event_params = array();
        $event_params['id'] = $this->getIdentity()->id;
        $this->di['events_manager']->fire(array('event'=>'onAfterAdminStaffProfilePasswordChange', 'params'=>$event_params));
        
        $this->di['logger']->info('Changed profile password');
        return true;
    }

    /**
     * Deletes admin login logs with given IDs
     *
     * @param array $ids - IDs for deletion
     *
     * @return bool
     */
    public function batch_delete_logs($data)
    {
        $required = array(
            'ids' => 'IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->login_history_delete(array('id' => $id));
        }

        return true;
    }
}