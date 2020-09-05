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
 * File manager
 *
 * All paths are relative to BoxBilling installation path
 * Files under BoxBilling installation path can not be managed
 */

namespace Box\Mod\Filemanager\Api;

class Admin extends \Api_Abstract
{
    /**
     * Save file contents
     *
     * @param string $path - path to the file
     * @param string $data - new file contents
     * @return bool
     */
    public function save_file($data)
    {
        $required = array(
            'path' => 'Path parameter is missing',
            'data' => 'Data parameter is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $content = empty($data['data']) ? PHP_EOL : $data['data'];

        return $this->getService()->saveFile($data['path'], $content);
    }

    /**
     * Create new file or directory
     *
     * @param string $path - item save path
     * @param string $type - item type: dir|file
     *
     * @return bool
     */
    public function new_item($data)
    {
        $required = array(
            'path' => 'Path parameter is missing',
            'type' => 'Type parameter is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->create($data['path'], $data['type']);
    }

    /**
     * Move/Rename file
     *
     * @param string $path - filepath to file which is going to be moved
     * @param string $to - new folder path. Do not include basename
     *
     * @return boolean
     */
    public function move_file($data)
    {
        $required = array(
            'path' => 'Path parameter is missing',
            'to'   => 'To parameter is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->move($data['path'], $data['to']);
    }

    /**
     * Get list of files in folder
     *
     * @optional string $path - directory path to be listed
     *
     * @return boolean
     */
    public function get_list($data)
    {
        $dir = isset($data['path']) ? (string)$data['path'] : DIRECTORY_SEPARATOR;

        return $this->getService()->getFiles($dir);
    }


}