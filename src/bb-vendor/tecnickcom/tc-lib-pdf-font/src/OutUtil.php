<?php
/**
 * OutUtil.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Com\Tecnick\Pdf\Font;

use \Com\Tecnick\File\Dir;
use \Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\OutUtil
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
abstract class OutUtil
{
    /**
     * Return font full path
     *
     * @param string $fontdir Original font directory
     * @param string $file    Font file name.
     *
     * @return string Font full path or empty string
     */
    protected function getFontFullPath($fontdir, $file)
    {
        $dirobj = new Dir();
        // directories where to search for the font definition file
        $dirs = array_unique(
            array(
                '',
                $fontdir,
                (defined('K_PATH_FONTS') ? K_PATH_FONTS : ''),
                $dirobj->findParentDir('fonts', __DIR__),
            )
        );
        foreach ($dirs as $dir) {
            if (@is_readable($dir.DIRECTORY_SEPARATOR.$file)) {
                return $dir.DIRECTORY_SEPARATOR.$file;
            }
        }
        throw new FontException('Unable to locate the file: '.$file);
    }

    /**
     * Outputs font widths
     *
     * @param array $font      Font to process
     * @param int   $cidoffset Offset for CID values
     *
     * @return string PDF command string for font widths
     */
    protected function getCharWidths(array $font, $cidoffset = 0)
    {
        ksort($font['cw']);
        $range = $this->getWidthRanges($font, $cidoffset);
        // output data
        $wdt = '';
        foreach ($range as $kdx => $wds) {
            if (count(array_count_values($wds)) == 1) {
                // interval mode is more compact
                $wdt .= ' '.$kdx.' '.($kdx + count($wds) - 1).' '.$wds[0];
            } else {
                // range mode
                $wdt .= ' '.$kdx.' [ '.implode(' ', $wds).' ]';
            }
        }
        return '/W ['.$wdt.' ]';
    }

    /**
     * get width ranges of characters
     *
     * @param array $font      Font to process
     * @param int   $cidoffset Offset for CID values
     *
     * @return array
     */
    protected function getWidthRanges(array $font, $cidoffset = 0)
    {
        $range = array();
        $rangeid = 0;
        $prevcid = -2;
        $prevwidth = -1;
        $interval = false;
        // for each character
        foreach ($font['cw'] as $cid => $width) {
            $cid -= $cidoffset;
            if ($font['subset'] && (!isset($font['subsetchars'][$cid]))) {
                // ignore the unused characters (font subsetting)
                continue;
            }
            if ($width != $font['dw']) {
                if ($cid == ($prevcid + 1)) {
                    // consecutive CID
                    if ($width == $prevwidth) {
                        if ($width == $range[$rangeid][0]) {
                            $range[$rangeid][] = $width;
                        } else {
                            array_pop($range[$rangeid]);
                            // new range
                            $rangeid = $prevcid;
                            $range[$rangeid] = array();
                            $range[$rangeid][] = $prevwidth;
                            $range[$rangeid][] = $width;
                        }
                        $interval = true;
                        $range[$rangeid]['interval'] = true;
                    } else {
                        if ($interval) {
                            // new range
                            $rangeid = $cid;
                            $range[$rangeid] = array();
                            $range[$rangeid][] = $width;
                        } else {
                            $range[$rangeid][] = $width;
                        }
                        $interval = false;
                    }
                } else {
                    // new range
                    $rangeid = $cid;
                    $range[$rangeid] = array();
                    $range[$rangeid][] = $width;
                    $interval = false;
                }
                $prevcid = $cid;
                $prevwidth = $width;
            }
        }
        return $this->optimizeWidthRanges($range);
    }

    /**
     * Optimize width ranges
     *
     * @param array $range Widht Ranges
     *
     * @return array
     */
    protected function optimizeWidthRanges($range)
    {
        $prevk = -1;
        $nextk = -1;
        $prevint = false;
        foreach ($range as $kdx => $wds) {
            $cws = count($wds);
            if (($kdx == $nextk) && (!$prevint) && ((!isset($wds['interval'])) || ($cws < 4))) {
                unset($range[$kdx]['interval']);
                $range[$prevk] = array_merge($range[$prevk], $range[$kdx]);
                unset($range[$kdx]);
            } else {
                $prevk = $kdx;
            }
            $nextk = $kdx + $cws;
            if (isset($wds['interval'])) {
                if ($cws > 3) {
                    $prevint = true;
                } else {
                    $prevint = false;
                }
                if (isset($range[$kdx]['interval'])) {
                    unset($range[$kdx]['interval']);
                }
                --$nextk;
            } else {
                $prevint = false;
            }
        }
        return $range;
    }
}
