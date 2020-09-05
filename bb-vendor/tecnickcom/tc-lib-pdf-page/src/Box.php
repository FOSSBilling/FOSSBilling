<?php
/**
 * Box.php
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

use \Com\Tecnick\Pdf\Page\Exception as PageException;

/**
 * Com\Tecnick\Pdf\Page\Box
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfPage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-page
 */
abstract class Box extends \Com\Tecnick\Pdf\Page\Mode
{
    /**
     * Array of page box names
     *
     * @var array
     */
    public static $box = array(
        'MediaBox',
        'CropBox',
        'BleedBox',
        'TrimBox',
        'ArtBox'
    );

    /**
     * Swap X and Y coordinates of page boxes (change page boxes orientation).
     *
     * @param array $dims Array of page dimensions.
     *
     * @return array Page dimensions.
     *
     */
    public function swapCoordinates(array $dims)
    {
        foreach (self::$box as $type) {
            // swap X and Y coordinates
            if (isset($dims[$type])) {
                $tmp = $dims[$type]['llx'];
                $dims[$type]['llx'] = $dims[$type]['lly'];
                $dims[$type]['lly'] = $tmp;
                $tmp = $dims[$type]['urx'];
                $dims[$type]['urx'] = $dims[$type]['ury'];
                $dims[$type]['ury'] = $tmp;
            }
        }
        return $dims;
    }

    /**
     * Set page boundaries.
     *
     * @param array  $dims    Array of page dimensions to modify
     * @param string $type    Box type: MediaBox, CropBox, BleedBox, TrimBox, ArtBox.
     * @param float  $llx     Lower-left x coordinate in user units.
     * @param float  $lly     Lower-left y coordinate in user units.
     * @param float  $urx     Upper-right x coordinate in user units.
     * @param float  $ury     Upper-right y coordinate in user units.
     * @param array  $bci     BoxColorInfo: guideline style (color, width, style, dash).
     *
     * @return array Page dimensions.
     */
    public function setBox($dims, $type, $llx, $lly, $urx, $ury, array $bci = array())
    {
        if (empty($dims)) {
            // initialize array
            $dims = array();
        }
        if (!in_array($type, self::$box)) {
            throw new PageException('unknown page box type: '.$type);
        }
        $dims[$type]['llx'] = $llx;
        $dims[$type]['lly'] = $lly;
        $dims[$type]['urx'] = $urx;
        $dims[$type]['ury'] = $ury;

        if (empty($bci)) {
            // set default values
            $bci = array(
                'color' => '#000000',
                'width' => (1.0 / $this->kunit),
                'style' => 'S', // S = solid; D = dash
                'dash'  => array(3)
            );
        }
        $dims[$type]['bci'] = $bci;

        return $dims;
    }

    /**
     * Initialize page boxes
     *
     * @param float $width  Page width in points
     * @param float $height Page height in points
     *
     * @return array Page boxes
     */
    public function setPageBoxes($width, $height)
    {
        $dims = array();
        foreach (self::$box as $type) {
            $dims = $this->setBox($dims, $type, 0, 0, $width, $height);
        }
        return $dims;
    }

    /**
     * Returns the PDF command to output the specified page boxes.
     *
     * @param array $dims Array of page dimensions.
     *
     * @return string
     */
    protected function getBox(array $dims)
    {
        $out = '';
        foreach (self::$box as $box) {
            if (empty($dims[$box])) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }
            $out .= '/'.$box.' ['.sprintf(
                '%F %F %F %F',
                $dims[$box]['llx'],
                $dims[$box]['lly'],
                $dims[$box]['urx'],
                $dims[$box]['ury']
            ).']'."\n";
        }
        return $out;
    }

    /**
     * Returns the PDF command to output the specified page BoxColorInfo
     *
     * @param array $dims Array of page dimensions.
     *
     * @return string
     */
    protected function getBoxColorInfo(array $dims)
    {
        $out = '/BoxColorInfo <<'."\n";
        foreach (self::$box as $box) {
            if (empty($dims[$box])) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }
            $out .= '/'.$box.' <<'."\n";
            if (!empty($dims[$box]['bci']['color'])) {
                $out .= '/C ['.$this->col->getPdfRgbComponents($dims[$box]['bci']['color']).']'."\n";
            }
            if (!empty($dims[$box]['bci']['width'])) {
                $out .= '/W '.sprintf('%F', ($dims[$box]['bci']['width'] * $this->kunit))."\n";
            }
            if (!empty($dims[$box]['bci']['style'])) {
                $mode = strtoupper($dims[$box]['bci']['style'][0]);
                if ($mode !== 'D') {
                    $mode = 'S';
                }
                $out .= '/S /'.$mode."\n";
            }
            if (!empty($dims[$box]['bci']['dash'])) {
                $out .= '/D [';
                foreach ($dims[$box]['bci']['dash'] as $dash) {
                    $out .= sprintf(' %F', ((float) $dash * $this->kunit));
                }
                $out .= ' ]'."\n";
            }
            $out .= '>>'."\n";
        }
        $out = '>>'."\n";
        return $out;
    }
}
