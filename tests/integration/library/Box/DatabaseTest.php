<?php
/**
 * @group Core
 */
class DatabaseTest extends BBDbApiTestCase
{

    public function testModel()
    {
        $model = $this->di['db']->dispense('Admin');
        $this->assertInstanceOf(RedBean_SimpleModel::class, $model);
        $id = $this->di['db']->store($model);
        
        $this->assertNotNull($id);
        $model = $this->di['db']->findOne('Admin', 'id = ?', array($id));
        $this->assertInstanceOf(RedBean_SimpleModel::class, $model);

        $model = $this->di['db']->load('Admin', $id);
        $this->assertInstanceOf(RedBean_SimpleModel::class, $model);

        $model = $this->di['db']->getExistingModelById('Admin', $id);
        $this->assertInstanceOf(RedBean_SimpleModel::class, $model);

        $array = $this->di['db']->toArray($model);
        $this->assertIsArray($array);

        $models = $this->di['db']->find('Admin');
        foreach($models as $m) {
            $this->assertInstanceOf(RedBean_SimpleModel::class, $m);
        }

        $this->assertNull($this->di['db']->trash($model));
    }

    public function testBean()
    {
        $bean = $this->di['db']->dispense('admin');
        $this->assertInstanceOf(RedBeanPHP\OODBBean::class, $bean);
        $id = $this->di['db']->store($bean);
        $this->assertNotNull($id);

        $model = $this->di['db']->findOne('admin', 'id = ?', array($id));
        $this->assertInstanceOf(RedBeanPHP\OODBBean::class, $model);

        $model = $this->di['db']->load('admin', $id);
        $this->assertInstanceOf(RedBeanPHP\OODBBean::class, $model);

        $model = $this->di['db']->getExistingModelById('admin', $id);
        $this->assertInstanceOf(RedBeanPHP\OODBBean::class, $model);

        $array = $this->di['db']->toArray($model);
        $this->assertIsArray($array);

        $models = $this->di['db']->find('admin');
        foreach($models as $m) {
            $this->assertInstanceOf(RedBeanPHP\OODBBean::class, $m);
        }

        $this->assertNull($this->di['db']->trash($model));
    }
}