<?php
/**
 * @group Core
 */
class Box_DatabaseIntegrationTest extends BBDbApiTestCase
{
    public function testModel()
    {
        $model = $this->di['db']->dispense('Admin');
        $this->assertInstanceOf('RedBean_SimpleModel', $model);
        $this->assertNotNull($this->di['db']->store($model));

        $model = $this->di['db']->findOne('Admin', 'id = ?', array(1));
        $this->assertInstanceOf('RedBean_SimpleModel', $model);

        $model = $this->di['db']->load('Admin', 1);
        $this->assertInstanceOf('RedBean_SimpleModel', $model);

        $model = $this->di['db']->getExistingModelById('Admin', 1);
        $this->assertInstanceOf('RedBean_SimpleModel', $model);

        $array = $this->di['db']->toArray($model);
        $this->assertInternalType('array', $array);

        $models = $this->di['db']->find('Admin');
        foreach($models as $m) {
            $this->assertInstanceOf('RedBean_SimpleModel', $m);
        }

        $this->assertNull($this->di['db']->trash($model));
    }

    public function testBean()
    {
        $bean = $this->di['db']->dispense('admin');
        $this->assertInstanceOf('RedBeanPHP\OODBBean', $bean);
        $this->assertNotNull($this->di['db']->store($bean));

        $model = $this->di['db']->findOne('admin', 'id = ?', array(1));
        $this->assertInstanceOf('RedBeanPHP\OODBBean', $model);

        $model = $this->di['db']->load('admin', 1);
        $this->assertInstanceOf('RedBeanPHP\OODBBean', $model);

        $model = $this->di['db']->getExistingModelById('admin', 1);
        $this->assertInstanceOf('RedBeanPHP\OODBBean', $model);

        $array = $this->di['db']->toArray($model);
        $this->assertInternalType('array', $array);

        $models = $this->di['db']->find('admin');
        foreach($models as $m) {
            $this->assertInstanceOf('RedBeanPHP\OODBBean', $m);
        }

        $this->assertNull($this->di['db']->trash($model));
    }
}