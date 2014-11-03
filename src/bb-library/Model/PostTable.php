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


class Model_PostTable implements \Box\InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di;

    /**
     * @param Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function getSearchQuery($data)
    {
        $sql = 'SELECT *
                FROM post
                WHERE 1 ';
        $params = array();

        $search = isset($data['search']) ? $data['search'] : NULL;
        $status = isset($data['status']) ? $data['status'] : NULL;
        
        if(NULL !== $status) {
            $sql .= ' status = :status ';
            $params[':status'] = $status;
        }

        if($search) {
            $sql .= ' title LIKE :title OR content :content ? ';
            $params[':title'] = "%$search%";
            $params[':content'] = "%$search%";
        }

        $sql .= ' ORDER BY id DESC';
        return array($sql, $params);
    }

    public function findOneActiveById($id)
    {
        return $this->di['db']->findOne('Post', 'id = ? and status = "active"', array($id));
    }

    public function findOneActiveBySlug($slug)
    {
        return $this->di['db']->findOne('Post', 'slug = ? and status = "active"', array($slug));
    }

    public function findActive()
    {
        return $this->di['db']->find('Post', 'active = 1');
    }

    public function getAuthorDetails(Model_Post $model)
    {
        $author = $this->di['db']->load('Admin', $model->admin_id);
        return array(
            'name'   =>  $author->getFullName(),
            'email'  =>  $author->email,
        );
    }

    public function rm(Model_Post $model)
    {
        $this->di['db']->trash($model);
    }

    public function toApiArray(Model_Post $model, $deep = true)
    {
        $data = $this->di['db']->toArray($model);
        $data['author'] = $this->getAuthorDetails($model);
        return $data;
    }
}