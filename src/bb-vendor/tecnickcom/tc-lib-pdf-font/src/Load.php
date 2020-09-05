<?php
/**
 * Load.php
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
use \Com\Tecnick\Pdf\Font\Core;
use \Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\Load
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
abstract class Load
{
    /**
     * Load the font data
     *
     * @throws FontException in case of error
     */
    public function load()
    {
        $fontInfo = $this->getFontInfo();
        $this->data = array_merge($this->data, $fontInfo);
        $this->checkType();
        $this->setName();
        $this->setDefaultWidth();
        if (($this->data['type'] == 'Core') || $this->data['fakestyle']) {
            $this->setArtificialStyles();
        }
        $this->setFileData();
    }

    /**
     * Load the font data
     *
     * @return array Font data
     *
     * @throws FontException in case of error
     */
    protected function getFontInfo()
    {
        $this->findFontFile();

        // read the font definition file
        if (!@is_readable($this->data['ifile'])) {
            throw new FontException('unable to read file: '.$this->data['ifile']);
        }

        $fdt = @file_get_contents($this->data['ifile']);
        $fdt = @json_decode($fdt, true);
        if ($fdt === null) {
            throw new FontException('JSON decoding error ['.json_last_error().']');
        }

        if (empty($fdt['type']) || empty($fdt['cw'])) {
            throw new FontException('fhe font definition file has a bad format: '.$this->data['ifile']);
        }

        return $fdt;
    }

    /**
     * Returns a list of font directories
     *
     * @return array Font directories
     */
    protected function findFontDirectories()
    {
        $dirobj = new Dir();
        $dirs =  array('');
        if (defined('K_PATH_FONTS')) {
            $dirs[] = K_PATH_FONTS;
            $dirs = array_merge($dirs, glob(K_PATH_FONTS.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR));
        }
        $parent_font_dir = $dirobj->findParentDir('fonts', __DIR__);
        if (!empty($parent_font_dir)) {
            $dirs[] = $parent_font_dir;
            $dirs = array_merge($dirs, glob($parent_font_dir.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR));
        }
        return array_unique($dirs);
    }

    /**
     * Load the font data
     *
     * @return array Font data
     *
     * @throws FontException in case of error
     */
    protected function findFontFile()
    {
        if (!empty($this->data['ifile'])) {
            return;
        }

        $this->data['ifile'] = strtolower($this->data['key']).'.json';
 
        // directories where to search for the font definition file
        $dirs = $this->findFontDirectories();

        // find font definition file names
        $files = array_unique(
            array(
                strtolower($this->data['key']).'.json',
                strtolower($this->data['family']).'.json'
            )
        );

        foreach ($files as $file) {
            foreach ($dirs as $dir) {
                if (@is_readable($dir.DIRECTORY_SEPARATOR.$file)) {
                    $this->data['ifile'] = $dir.DIRECTORY_SEPARATOR.$file;
                    $this->data['dir'] = $dir;
                    break 2;
                }
            }
            // we haven't found the version with style variations
            $this->data['fakestyle'] = true;
        }
    }

    /**
     * Set default width
     */
    protected function setDefaultWidth()
    {
        if (!empty($this->data['dw'])) {
            return;
        }
        if (isset($this->data['desc']['MissingWidth']) && ($this->data['desc']['MissingWidth'] > 0)) {
            $this->data['dw'] = $this->data['desc']['MissingWidth'];
        } elseif (!empty($this->data['cw'][32])) {
            $this->data['dw'] = $this->data['cw'][32];
        } else {
            $this->data['dw'] = 600;
        }
    }

    /**
     * Check Font Type
     */
    protected function checkType()
    {
        if (in_array($this->data['type'], array('Core', 'Type1', 'TrueType', 'TrueTypeUnicode', 'cidfont0'))) {
            return;
        }
        throw new FontException('Unknow font type: '.$this->data['type']);
    }

    /**
     * Set name
     */
    protected function setName()
    {
        if ($this->data['type'] == 'Core') {
            $this->data['name'] = Core::$font[$this->data['key']];
            $this->data['subset'] = false;
        } elseif (($this->data['type'] == 'Type1') || ($this->data['type'] == 'TrueType')) {
            $this->data['subset'] = false;
        } elseif ($this->data['type'] == 'TrueTypeUnicode') {
            $this->data['enc'] = 'Identity-H';
        } elseif (($this->data['type'] == 'cidfont0') && ($this->data['pdfa'])) {
            throw new FontException('CID0 fonts are not supported, all fonts must be embedded in PDF/A mode!');
        }
        if (empty($this->data['name'])) {
            $this->data['name'] = $this->data['key'];
        }
    }

    /**
     * Set artificial styles if the font variation file is missing
     */
    protected function setArtificialStyles()
    {
        // artificial bold
        if ($this->data['mode']['bold']) {
            $this->data['name'] .= 'Bold';
            if (isset($this->data['desc']['StemV'])) {
                $this->data['desc']['StemV'] = round($this->data['desc']['StemV'] * 1.75);
            } else {
                $this->data['desc']['StemV'] = 123;
            }
        }
        // artificial italic
        if ($this->data['mode']['italic']) {
            $this->data['name'] .= 'Italic';
            if (isset($this->data['desc']['ItalicAngle'])) {
                $this->data['desc']['ItalicAngle'] -= 11;
            } else {
                $this->data['desc']['ItalicAngle'] = -11;
            }
            if (isset($this->data['desc']['Flags'])) {
                $this->data['desc']['Flags'] |= 64; //bit 7
            } else {
                $this->data['desc']['Flags'] = 64;
            }
        }
    }

    /**
     * Set File data
     */
    public function setFileData()
    {
        if (empty($this->data['file'])) {
            return;
        }
        if (strpos($this->data['type'], 'TrueType') !== false) {
            $this->data['length1'] = $this->data['originalsize'];
            $this->data['length2'] = false;
        } elseif ($this->data['type'] != 'Core') {
            $this->data['length1'] = $this->data['size1'];
            $this->data['length2'] = $this->data['size2'];
        }
    }
}
