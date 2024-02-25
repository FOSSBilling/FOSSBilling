<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\InjectionAwareInterface;

class Box_Database implements InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;
    protected $orm;

    public function setDataMapper($orm)
    {
        $this->orm = $orm;
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public function dispense($modelName)
    {
        $type = $this->_getTypeFromModelName($modelName);
        $bean = $this->orm->dispense($type);
        if ($type == $modelName) {
            return $bean;
        }

        return $bean->box();
    }

    public function store($modelOrBean)
    {
        if ($modelOrBean instanceof RedBeanPHP\SimpleModel) {
            $bean = $modelOrBean->unbox();
        } else {
            $bean = $modelOrBean;
        }

        return $this->orm->store($bean);
    }

    public function getAll($sql, $values = [])
    {
        return $this->orm->getAll($sql, $values);
    }

    public function getCell($sql, $values = [])
    {
        return $this->orm->getCell($sql, $values);
    }

    public function getRow($sql, $values = [])
    {
        return $this->orm->getRow($sql, $values);
    }

    public function getAssoc($sql, $values = [])
    {
        return $this->orm->getAssoc($sql, $values);
    }

    public function findOne($modelName, $sql = null, $values = [])
    {
        $type = $this->_getTypeFromModelName($modelName);
        $bean = $this->orm->findOne($type, $sql, $values);
        if ($type == $modelName) {
            return $bean;
        }
        if ($bean && $bean->id) {
            return $bean->box();
        }

        return null;
    }

    public function find($modelName, $sql = null, $values = [])
    {
        $type = $this->_getTypeFromModelName($modelName);
        $beans = $this->orm->find($type, $sql, $values);
        if ($type == $modelName) {
            return $beans;
        }
        foreach ($beans as &$bean) {
            $bean = $bean->box();
        }

        return $beans;
    }

    public function findAll(string $table, string $sql = null, array $bindings = [])
    {
        if (is_null($sql)) {
            return $this->orm->findAll($table);
        } else {
            return $this->orm->findAll($table, $sql, $bindings);
        }
    }

    /**
     * @param string $modelName
     * @param int    $id
     */
    public function load($modelName, $id)
    {
        /* If RedBean finds the bean it will return
         * the OODB Bean object; if it cannot find the bean
         * RedBean will return a new bean of type $modelName and with
         * primary key ID 0. In the latter case it acts basically the
         * same as dispense().
         */

        $type = $this->_getTypeFromModelName($modelName);
        $bean = $this->orm->load($type, $id);
        if ($type == $modelName) {
            return $bean;
        }
        if ($bean && $bean->id) {
            return $bean->box();
        }

        return null;
    }

    /**
     * @param string $sql
     * @param array  $values
     *
     * @return int - affected rows
     */
    public function exec($sql, $values = [])
    {
        return $this->orm->exec($sql, $values);
    }

    public function trash($modelOrBean)
    {
        if ($modelOrBean instanceof RedBeanPHP\SimpleModel) {
            $bean = $modelOrBean->unbox();
        } else {
            $bean = $modelOrBean;
        }

        return $this->orm->trash($bean);
    }

    public function getInsertID()
    {
        return $this->di['pdo']->lastInsertId();
    }

    public function getColumns($table)
    {
        return $this->orm->getColumns($table);
    }

    public function toArray($modelOrBean)
    {
        if ($modelOrBean instanceof RedBeanPHP\SimpleModel) {
            $bean = $modelOrBean->unbox();
        } else {
            $bean = $modelOrBean;
        }

        return $bean->export();
    }

    /**
     * @param string $modelName
     * @param int    $id
     * @param string $message
     *
     * @return RedBeanPHP\SimpleModel
     *
     * @throws FOSSBilling\Exception
     */
    public function getExistingModelById($modelName, $id, $message = 'Model :name not found in the database')
    {
        $model = $this->load($modelName, (int) $id);
        if ($model === null) {
            throw new FOSSBilling\Exception($message, [':name' => $modelName]);
        }

        return $model;
    }

    private function _getTypeFromModelName($modelName)
    {
        if ($modelName == strtolower($modelName)) {
            return $modelName;
        }

        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $modelName, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }

        return implode('_', $ret);
    }
}
