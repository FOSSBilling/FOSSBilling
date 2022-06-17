<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * All public methods in this class are exposed to public. Always think
 * what kind of information you are exposing. Emails, passwords and other
 * information should NOT be returned by functions in this class.
 *
 * This module can be called from API or in template
 */

 // Change "Example" with your module's name

namespace Box\Mod\Example\Api;

class Guest extends \Api_Abstract
{
    /**
     * Return extension README.
     *
     * @return string
     */
    public function readme($data)
    {
        // $this->di['tools'] returns tools from /bb-library/Box/Tools.php
        // We'll be using the file_get_contents from our tools to fetch the full content of the README file
        // Our example admin and client area pages will use this function to fetch the README data
        // Then, we'll tell Twig to parse and display the markdown output

        $readme = $this->di['tools']->file_get_contents(BB_PATH_MODS.'/Example/README.md');

        return $readme;
    }

    /**
     * Return a random number between 1 and 100.
     *
     * @return int
     */
    public function random_number()
    {
        return random_int(1, 100);
    }
}
