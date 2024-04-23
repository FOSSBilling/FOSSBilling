<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_BeanHelper extends RedBeanPHP\BeanHelper\SimpleFacadeBeanHelper implements FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    /** @phpstan-ignore-next-line (No matter what I put for the return type of this function, PHPStan is unhappy) */
    public function getModelForBean(RedBeanPHP\OODBBean $bean): ?object
    {
        $prefix = '\\Model_';
        $model = $bean->getMeta('type');
        $modelName = $prefix . $this->underscoreToCamelCase($model);

        if (!class_exists($modelName)) {
            return null;
        }

        $model = new $modelName();
        if ($model instanceof FOSSBilling\InjectionAwareInterface) {
            $model->setDi($this->di);
        }

        $model->loadBean($bean);

        return $model;
    }

    private function underscoreToCamelCase($string, $first_char_caps = true)
    {
        if ($first_char_caps === true) {
            $string[0] = strtoupper($string[0]);
        }
        $func = fn ($c): string => strtoupper($c[1]);

        return preg_replace_callback('/_([a-z])/', $func, $string);
    }
}
