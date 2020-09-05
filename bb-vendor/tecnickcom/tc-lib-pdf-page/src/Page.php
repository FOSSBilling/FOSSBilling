<?php
/**
 * Page.php
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
use \Com\Tecnick\Pdf\Encrypt\Encrypt;
use \Com\Tecnick\Pdf\Page\Exception as PageException;

/**
 * Com\Tecnick\Pdf\Page\Page
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfPage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-page
 */
class Page extends \Com\Tecnick\Pdf\Page\Region
{
    /**
     * Alias for total number of pages in a group
     *
     * @var string
     */
    const PAGE_TOT = '~#PT';
    
    /**
     * Alias for page number
     *
     * @var string
     */
    const PAGE_NUM = '~#PN';

    /**
     * Array of pages (stack).
     *
     * @var array
     */
    protected $page = array();

    /**
     * Current page ID.
     *
     * @var int
     */
    protected $pageid = -1;

    /**
     * Maximum page ID.
     *
     * @var int
     */
    protected $pmaxid = -1;
    
    /**
     * Count pages in each group
     *
     * @var array
     */
    protected $group = array(0 => 0);
    
    /**
     * Unit of measure conversion ratio
     *
     * @var float
     */
    protected $kunit = 1.0;

    /**
     * Color object
     *
     * @var Color
     */
    protected $col;

    /**
     * Encrypt object
     *
     * @var Encrypt
     */
    protected $enc;

    /**
     * True if we are in PDF/A mode.
     *
     * @var bool
     */
    protected $pdfa = false;

    /**
     * True if the signature approval is enabled (for incremental updates).
     *
     * @var bool
     */
    protected $sigapp = false;

    /**
     * Reserved Object ID for the resource dictionary.
     *
     * @var int
     */
    protected $rdoid = 1;

    /**
     * Initialize page data
     *
     * @param string  $unit   Unit of measure ('pt', 'mm', 'cm', 'in')
     * @param Color   $col    Color object
     * @param Encrypt $enc    Encrypt object
     * @param bool    $pdfa   True if we are in PDF/A mode.
     * @param bool    $sigapp True if the signature approval is enabled (for incremental updates).
     */
    public function __construct($unit, Color $col, Encrypt $enc, $pdfa = false, $sigapp = false)
    {
        $this->kunit = $this->getUnitRatio($unit);
        $this->col = $col;
        $this->enc = $enc;
        $this->pdfa = (bool) $pdfa;
        $this->sigapp = (bool) $sigapp;
    }

    /**
     * Get the unit ratio
     *
     * @return float
     */
    public function getKUnit()
    {
        return $this->kunit;
    }

    /**
     * Enable Signature Approval
     *
     * @param bool $sigapp True if the signature approval is enabled (for incremental updates).
     */
    public function enableSignatureApproval($sigapp)
    {
        $this->sigapp = (bool) $sigapp;
        return $this;
    }

    /**
     * Add a new page
     *
     * @param array $data Page data:
     *     time        : UTC page modification time in seconds;
     *     group       : page group number;
     *     num         : if set overwrites the page number;
     *     content     : string containing the raw page content;
     *     annotrefs   : array containing the annotation object references;
     *     format      : page format name, or alternatively you can set width and height as below;
     *     width       : page width;
     *     height      : page height;
     *     orientation : page orientation ('P' or 'L');
     *     rotation    : the number of degrees by which the page shall be rotated clockwise when displayed or printed;
     *     box         : array containing page box boundaries and settings (@see setBox);
     *     transition  : array containing page transition data (@see getPageTransition);
     *     zoom        : preferred zoom (magnification) factor;
     *     margin      : page margins:
     *                   PL : page left margin measured from the left page edge
     *                   PR : page right margin measured from the right page edge
     *                   PT : page top or header top measured distance from the top page edge
     *                   HB : header bottom measured from the top page edge
     *                   CT : content top measured from the top page edge
     *                   CB : content bottom (page breaking point) measured from the top page edge
     *                   FT : footer top measured from the bottom page edge
     *                   PB : page bottom (footer bottom) measured from the bottom page edge
     *     columns     : number of equal vertical columns, if set it will automatically populate the region array
     *     region      : array containing the ordered list of rectangular areas where it is allowed to write,
     *                   each region is defined by:
     *                   RX : horizontal coordinate of top-left corner
     *                   RY : vertical coordinate of top-left corner
     *                   RW : region width
     *                   RH : region height
     *     autobreak   : true to automatically add a page when the content reaches the breaking point.
     *
     * NOTE: if $data is empty, then the last page format will be cloned.
     *
     * @return array Page data
     */
    public function add(array $data = array())
    {
        if (empty($data) && ($this->pmaxid >= 0)) {
            // clone last page data
            $data = $this->page[$this->pmaxid];
            unset($data['time'], $data['content'], $data['annotrefs'], $data['pagenum']);
        } else {
            $this->sanitizeGroup($data);
            $this->sanitizeRotation($data);
            $this->sanitizeZoom($data);
            $this->sanitizePageFormat($data);
            $this->sanitizeBoxData($data);
            $this->sanitizeTransitions($data);
            $this->sanitizeMargins($data);
            $this->sanitizeRegions($data);
        }

        $this->sanitizeTime($data);
        $this->sanitizeContent($data);
        $this->sanitizeAnnotRefs($data);
        $this->sanitizePageNumber($data);
        $data['content_mark'] = array(0);
        $data['currentRegion'] = 0;

        $this->pageid = ++$this->pmaxid;
        $this->page[$this->pageid] = $data;
        if (isset($this->group[$data['group']])) {
            $this->group[$data['group']] += 1;
        } else {
            $this->group[$data['group']] = 1;
        }

        return $this->page[$this->pageid];
    }

    /**
     * Remove the specified page
     *
     * @param int $idx page index
     *
     * @return array Removed page
     */
    public function delete($idx)
    {
        if (empty($this->page[$idx])) {
            throw new PageException('The specified page do not exist');
        }
        $page = $this->page[$idx];
        $this->group[$this->page[$idx]['group']] -= 1;
        unset($this->page[$idx]);
        $this->page = array_values($this->page); // reindex array
        --$this->pmaxid;
        return $page;
    }

    /**
     * Remove and return last page
     *
     * @return array Removed page
     */
    public function pop()
    {
        return $this->delete($this->pmaxid);
    }

    /**
     * Move a page to a previous position
     *
     * @param int $from Index of the page to move
     * @param int $new  Destination index
     */
    public function move($from, $new)
    {
        if (($from <= $new) || ($from > $this->pmaxid)) {
            throw new PageException('The new position must be lower than the starting position');
        }
        $this->page = array_values(
            array_merge(
                array_slice($this->page, 0, $new),
                array($this->page[$from]),
                array_slice($this->page, $new, ($from - $new)),
                array_slice($this->page, ($from + 1))
            )
        );
    }

    /**
     * Returns the array (stack) containing all pages data.
     *
     * return array
     */
    public function getPages()
    {
        return $this->page;
    }

    /**
     * Returns the specified page data.
     *
     * @param int $idx Page ID
     *
     * return array
     */
    public function getPage($idx)
    {
        if (!isset($this->page[$idx])) {
            throw new PageException('The page '.$idx.' do not exist.');
        }
        return $this->page[$idx];
    }

    /**
     * Set the current page number (move to the specified page)
     *
     * @param int $pid Page ID number
     */
    public function setCurrentPage($pid)
    {
        $this->pageid = min(max(0, intval($pid)), $this->pmaxid);
        return $this->page[$this->pageid];
    }

    /**
     * Returns the last page array
     *
     * @return array
     */
    public function getCurrentPage()
    {
        return $this->page[$this->pageid];
    }

    /**
     * Add page content
     *
     * @param array $data Page data
     */
    public function addContent($content)
    {
        $this->page[$this->pageid]['content'][] = (string) $content;
    }

    /**
     * Remove and return last page content
     *
     * @param array $data Page data
     *
     * @param string content
     */
    public function popContent()
    {
        return array_pop($this->page[$this->pageid]['content']);
    }

    /**
     * Add page content mark
     */
    public function addContentMark()
    {
        $this->page[$this->pageid]['content_mark'][] = count($this->page[$this->pageid]['content']);
    }

    /**
     * Remove the last marked page content
     */
    public function popContentToLastMark()
    {
        $mark = array_pop($this->page[$this->pageid]['content_mark']);
        $this->page[$this->pageid]['content'] = array_slice($this->page[$this->pageid]['content'], 0, $mark, true);
    }

    /**
     * Returns the PDF command to output all page sections
     *
     * @param int $pon Current PDF object number
     *
     * @return string PDF command
     */
    public function getPdfPages(&$pon)
    {
        $out = $this->getPageRootObj($pon);
        $rootobjid = ($this->rdoid - 1);

        foreach ($this->page as $num => $page) {
            if (!isset($page['num'])) {
                if ($num > 0) {
                    if ($page['group'] == $this->page[($num - 1)]['group']) {
                        $page['num'] = (1 + $this->page[($num - 1)]['num']);
                    } else {
                        // new page group
                        $page['num'] = 1;
                    }
                } else {
                    $page['num'] = (1 + $num);
                }
            }
            $this->page[$num]['num'] = $page['num'];
            
            $content = $this->replacePageTemplates($page);
            $out .= $this->getPageContentObj($pon, $content);
            $contentobjid = $pon;

            $out .= $page['n'].' 0 obj'."\n"
                .'<<'."\n"
                .'/Type /Page'."\n"
                .'/Parent '.$rootobjid.' 0 R'."\n";
            if (!$this->pdfa) {
                $out .= '/Group << /Type /Group /S /Transparency /CS /DeviceRGB >>'."\n";
            }
            if (!$this->sigapp) {
                $out .= '/LastModified '.$this->enc->getFormattedDate($page['time'], $pon)."\n";
            }
            $out .= '/Resources '.$this->rdoid.' 0 R'."\n"
                .$this->getBox($page['box'])
                .$this->getBoxColorInfo($page['box'])
                .'/Contents '.$contentobjid.' 0 R'."\n"
                .'/Rotate '.$page['rotation']."\n"
                .'/PZ '.sprintf('%F', $page['zoom'])."\n"
                .$this->getPageTransition($page)
                .$this->getAnnotationRef($page)
                .'>>'."\n"
                .'endobj'."\n";
        }

        return $out;
    }

    /**
     * Returns the reserved Object ID for the Resource dictionary.
     *
     * return int
     */
    public function getResourceDictObjID()
    {
        return $this->rdoid;
    }

    /**
     * Returns the PDF command to output the page content.
     *
     * @param int    $pon     Current PDF object number.
     * @param string $content Page content.
     *
     * @return string PDF command
     */
    protected function getPageTransition($page)
    {
        if (empty($page['transition'])) {
            return '';
        }
        $entries = array('S', 'D', 'Dm', 'M', 'Di', 'SS', 'B');
        $out = '';
        if (isset($page['transition']['Dur'])) {
            $out .= '/Dur '.sprintf('%F', $page['transition']['Dur'])."\n";
        }
        $out .= '/Trans <<'."\n"
            .'/Type /Trans'."\n";
        foreach ($page['transition'] as $key => $val) {
            if (in_array($key, $entries)) {
                if (is_float($val)) {
                    $val = sprintf('%F', $val);
                }
                $out .= '/'.$key.' /'.$val."\n";
            }
        }
        $out .= '>>'."\n";
        return $out;
    }

    /**
     * Get references to page annotations.
     *
     * @param array $page Page data
     *
     * @return string PDF command
     */
    protected function getAnnotationRef($page)
    {
        if (empty($page['annotrefs'])) {
            return '';
        }
        $out = '/Annots [ ';
        foreach ($page['annotrefs'] as $val) {
            $out .= intval($val).' 0 R ';
        }
        $out .= ']'."\n";
        return $out;
    }

    /**
     * Returns the PDF command to output the page content.
     *
     * @param int    $pon     Current PDF object number.
     * @param string $content Page content.
     *
     * @return string PDF command
     */
    protected function getPageContentObj(&$pon, $content = '')
    {
        $stream = $this->enc->encryptString(gzcompress($content), ++$pon);
        $out = $pon.' 0 obj'."\n"
            .'<</Filter /FlateDecode /Length '.strlen($stream).'>>'."\n"
            .'stream'."\n"
            .$stream."\n"
            .'endstream'."\n"
            .'endobj'."\n";
        return $out;
    }

    /**
     * Returns the PDF command to output the page root object.
     *
     * @param int $pon Current PDF object number
     *
     * @return string PDF command
     */
    protected function getPageRootObj(&$pon)
    {
        $out = (++$pon).' 0 obj'."\n";
        $this->rdoid = ++$pon; // reserve object ID for the resource dictionary
        $out .= '<< /Type /Pages /Kids [ ';
        $numpages = count($this->page);
        for ($idx = 0; $idx < $numpages; ++$idx) {
            $this->page[$idx]['n'] = ++$pon;
            $out .= $this->page[$idx]['n'].' 0 R ';
        }
        $out .= '] /Count '.$numpages.' >>'."\n"
            .'endobj'."\n";
        return $out;
    }

    /**
     * Replace page templates and numbers
     *
     * @param array $data Page data
     */
    protected function replacePageTemplates(array $data)
    {
        return implode(
            '',
            str_replace(
                array(self::PAGE_TOT, self::PAGE_NUM),
                array($this->group[$data['group']], $data['num']),
                $data['content']
            )
        );
    }
}
