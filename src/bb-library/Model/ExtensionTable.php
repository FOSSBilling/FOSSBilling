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


class Model_ExtensionTable implements \Box\InjectionAwareInterface
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

    public function getModulesCount()
    {
        $query = "SELECT COUNT(id) as cc
                FROM extension
                WHERE `type` = 'mod'
                GROUP BY `type`
               ";
        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_COLUMN);
    }

    public function getModule($mod)
    {
        return $this->di['db']->findOne('extension', 'type = "mod" and name = ?', array($mod));
    }
}