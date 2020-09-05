<?php
/**
 * Region.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfPage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-page
 *
 * This file is part of tc-lib-pdf-page software library.
 */

namespace Com\Tecnick\Pdf\Page;

use \Com\Tecnick\Color\Pdf as Color;
use \Com\Tecnick\Pdf\Page\Exception as PageException;

/**
 * Com\Tecnick\Pdf\Page\Region
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfPage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-page
 *
 * A page region defines the writable area of the page.
 */
abstract class Region extends \Com\Tecnick\Pdf\Page\Settings
{
    /**
     * Select the specified page region.
     *
     * @return array Selected region data
     */
    public function selectRegion($idr)
    {
        $this->page[$this->pageid]['currentRegion'] = min(max(0, intval($idr)), $this->page[$this->pageid]['columns']);
        return $this->getCurrentRegion();
    }

    /**
     * Returns the current region data
     *
     * @return array
     */
    public function getCurrentRegion()
    {
        return $this->page[$this->pageid]['region'][$this->page[$this->pageid]['currentRegion']];
    }

    /**
     * Returns the page data with the next selected region.
     * If there are no more regions available,
     * then the first region on the next page is selected.
     * A new page is added if required.
     *
     * @return array Current page data
     */
    public function getNextRegion()
    {
        $nextid = ($this->page[$this->pageid]['currentRegion'] + 1);
        if (isset($this->page[$this->pageid]['region'][$nextid])) {
            $this->page[$this->pageid]['currentRegion'] = $nextid;
            return $this->page[$this->pageid];
        }
        return $this->getNextPage();
    }

    /**
     * Returns the next page data.
     * Creates a new page if required and page break is enabled.
     *
     * @return array Page data
     */
    public function getNextPage()
    {
        if ($this->pageid < $this->pmaxid) {
            return $this->page[++$this->pageid];
        }
        if (!$this->isAutoPageBreakEnabled()) {
            return $this->getCurrentPage();
        }
        return $this->add();
    }

    /**
     * Move to the next page region if required.
     *
     * @param float $height Height of the block to add.
     * @param float $ypos   Starting Y position or NULL for current position.
     *
     * @return array Page data
     */
    public function checkRegionBreak($height = 0, $ypos = null)
    {
        if ($this->isYOutRegion($ypos, $height)) {
            return $this->getNextRegion();
        }
        return $this->getCurrentPage();
    }

    /**
     * Return the auto-page-break status
     *
     * @return bool True if the auto page break is enabled, false otherwise.
     */
    public function isAutoPageBreakEnabled()
    {
        return $this->page[$this->pageid]['autobreak'];
    }

    /**
     * Enable or disable automatic page break.
     *
     * @param bool $isenabled Set this to true to enable automatic page break.
     */
    public function enableAutoPageBreak($isenabled = true)
    {
        $this->page[$this->pageid]['autobreak'] = (bool) $isenabled;
    }

    /**
     * Check if the specified position is outside the region.
     *
     * @param float  $pos Position
     * @param string $min ID of the min region value to check
     * @param string $max ID of the max region value to check
     *
     * @return boolean
     */
    private function isOutRegion($pos, $min, $max)
    {
        $region = $this->getCurrentRegion();
        if (($pos < ($region[$min] - self::EPS)) || ($pos > ($region[$max] + self::EPS))) {
            return true;
        }
        return false;
    }

    /**
     * Check if the specified vertical position is outside the region.
     *
     * @param float $posy   Y position or NULL for current position.
     * @param float $height Additional height to add.
     *
     * @return boolean
     */
    public function isYOutRegion($posy = null, $height = 0)
    {
        if ($posy === null) {
            $posy = $this->getY();
        }
        return $this->isOutRegion(floatval($posy + $height), 'RY', 'RT');
    }

    /**
     * Check if the specified horizontal position is outside the region.
     *
     * @param float $posx  X position or NULL for current position.
     * @param float $width Additional width to add.
     *
     * @return boolean
     */
    public function isXOutRegion($posx = null, $width = 0)
    {
        if ($posx === null) {
            $posx = $this->getX();
        }
        return $this->isOutRegion(floatval($posx + $width), 'RX', 'RL');
    }

    /**
     * Return the absolute horizontal cursor position for the current region.
     *
     * @return float
     */
    public function getX()
    {
        return $this->page[$this->pageid]['region'][$this->page[$this->pageid]['currentRegion']]['x'];
    }

    /**
     * Return the absolute vertical cursor position for the current region.
     *
     * @return float
     */
    public function getY()
    {
        return $this->page[$this->pageid]['region'][$this->page[$this->pageid]['currentRegion']]['y'];
    }

    /**
     * Set the absolute horizontal cursor position for the current region.
     *
     * @param foat $xpos X position relative to the page coordinates.
     */
    public function setX($xpos)
    {
        $this->page[$this->pageid]['region'][$this->page[$this->pageid]['currentRegion']]['x'] = floatval($xpos);
        return $this;
    }

    /**
     * Set the absolute vertical cursor position for the current region.
     *
     * @param foat $ypos Y position relative to the page coordinates.
     */
    public function setY($ypos)
    {
        $this->page[$this->pageid]['region'][$this->page[$this->pageid]['currentRegion']]['y'] = floatval($ypos);
        return $this;
    }
}
