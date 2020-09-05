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


class Box_RequestFile extends SplFileInfo
{
    protected $name;


    public function __construct(array $file)
    {
        $this->name = $file['name'];
        parent::__construct($file['tmp_name']);
    }


    public function getName()
    {
        return $this->name;
    }
}