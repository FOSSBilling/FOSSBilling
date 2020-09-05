<?php
/**
 * Stack.php
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

use \Com\Tecnick\Pdf\Font\Font;
use \Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\Stack
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
class Stack extends \Com\Tecnick\Pdf\Font\Buffer
{
    /**
     * Default font size in points
     */
    const DEFAULT_SIZE = 10;

    /**
     * Array (stack) containing fonts in order of insertion.
     * The last item is the current font.
     *
     * @var array
     */
    protected $stack = array();

    /**
     * Current font index
     *
     * @var int
     */
    protected $index = -1;

    /**
     * Array containing font metrics for each fontkey-size combination.
     *
     * @var array
     */
    protected $metric = array();

    /**
     * Insert a font into the stack
     *
     * The definition file (and the font file itself when embedding) must be present either in the current directory
     * or in the one indicated by K_PATH_FONTS if the constant is defined.
     *
     * @param int    $objnum     Current PDF object number
     * @param string $font       Font family, or comma separated list of font families
     *                           If it is a standard family name, it will override the corresponding font.
     * @param string $style      Font style.
     *                           Possible values are (case insensitive):
     *                             regular (default)
     *                             B: bold
     *                             I: italic
     *                             U: underline
     *                             D: strikeout (linethrough)
     *                             O: overline
     * @param int    $size       Font size in points (set to null to inherit the last font size).
     * @param float  $spacing    Extra spacing between characters.
     * @param float  $stretching Horizontal character stretching ratio.
     * @param string $ifile      The font definition file (or empty for autodetect).
     *                           By default, the name is built from the family and style, in lower case with no spaces.
     * @param bool   $subset     If true embedd only a subset of the font
     *                           (stores only the information related to the used characters);
     *                           If false embedd full font;
     *                           This option is valid only for TrueTypeUnicode fonts and it is disabled for PDF/A.
     *                           If you want to enable users to modify the document, set this parameter to false.
     *                           If you subset the font, the person who receives your PDF would need to have
     *                           your same font in order to make changes to your PDF.
     *                           The file size of the PDF would also be smaller because you are embedding only a subset.
     *                           Set this to null to use the default value.
     *                           NOTE: This option is computational and memory intensive.
     *
     * @return array Font data
     *
     * @throws FontException in case of error
     */
    public function insert(
        &$objnum,
        $font,
        $style = '',
        $size = null,
        $spacing = null,
        $stretching = null,
        $ifile = '',
        $subset = null
    ) {
        if ($subset === null) {
            $subset = $this->subset;
        }
        $size = $this->getInputSize($size);
        $spacing = $this->getInputSpacing($spacing);
        $stretching = $this->getInputStretching($stretching);

        // try to load the corresponding imported font
        $err = null;
        $keys = $this->getNormalizedFontKeys($font);
        $fontkey = '';
        foreach ($keys as $fkey) {
            try {
                $fontkey = $this->add($objnum, $fkey, $style, $ifile, $subset);
                $err = null;
                break;
            } catch (FontException $exc) {
                $err = $exc;
            }
        }
        if ($err !== null) {
            throw new FontException($err->getMessage());
        }

        // add this font in the stack
        $data = $this->getFont($fontkey);

        $this->stack[++$this->index] = array(
            'key'        => $fontkey,
            'style'      => $data['style'],
            'size'       => $size,
            'spacing'    => $spacing,
            'stretching' => $stretching,
        );

        return $this->getFontMetric($this->stack[$this->index]);
    }

    /**
     * Returns the current font data array
     *
     * @return array
     */
    public function getCurrentFont()
    {
        return $this->getFontMetric($this->stack[$this->index]);
    }

    /**
     * Remove and return the last inserted font
     *
     * @return array
     */
    public function popLastFont()
    {
        if ($this->index < 0) {
            throw new FontException('The font stack is empty');
        }
        $font = array_pop($this->stack);
        --$this->index;
        return $this->getFontMetric($font);
    }

    /**
     * Replace missing characters with selected substitutions
     *
     * @param array $uniarr Array of character codepoints.
     * @param array $subs   Array of possible character substitutions.
     *                      The key is the character to check (integer value),
     *                      the value is an array of possible substitutes.
     *
     * @return array
     */
    public function replaceMissingChars(array $uniarr, array $subs = array())
    {
        $font = $this->getFontMetric($this->stack[$this->index]);
        foreach ($uniarr as $pos => $uni) {
            if (isset($font['cw'][$uni]) || !isset($subs[$uni])) {
                continue;
            }
            foreach ($subs[$uni] as $alt) {
                if (isset($font['cw'][$alt])) {
                    $uniarr[$pos] = $alt;
                    break;
                }
            }
        }
        return $uniarr;
    }

    /**
     * Returns true if the specified unicode value is defined in the current font
     *
     * @param int $ord Unicode character value to convert
     *
     * @return bool
     */
    public function isCharDefined($ord)
    {
        $font = $this->getFontMetric($this->stack[$this->index]);
        return isset($font['cw'][$ord]);
    }

    /**
     * Returns true if the specified unicode value is defined in the current font
     *
     * @param int   $ord    Unicode character value.
     *
     * @return int
     */
    public function getCharWidth($ord)
    {
        if ($ord == 173) {
            // SHY character is not printed, as it is used for text hyphenation
            return 0;
        }
        $font = $this->getFontMetric($this->stack[$this->index]);
        if (isset($font['cw'][$ord])) {
            return $font['cw'][$ord];
        }
        return $font['dw'];
    }

    /**
     * Returns the lenght of the string specified using an array of codepoints.
     *
     * @param array $uniarr Array of character codepoints.
     *
     * @return float
     */
    public function getOrdArrWidth($uniarr)
    {
        $width = 0;
        foreach ($uniarr as $ord) {
            $width += $this->GetCharWidth($ord);
        }
        $width += ($this->stack[$this->index]['spacing']
            * $this->stack[$this->index]['stretching']
            * (count($uniarr) - 1)
        );
        return $width;
    }

    /**
     * Returns the glyph bounding box of the specified character in the current font in user units.
     *
     * @param int $ord Unicode character value.
     *
     * @return array (xMin, yMin, xMax, yMax)
     */
    public function getCharBBox($ord)
    {
        $font = $this->getFontMetric($this->stack[$this->index]);
        if (isset($font['cbbox'][$ord])) {
            return $font['cbbox'][$ord];
        }
        return array(0, 0, 0, 0); // glyph without outline
    }

    /**
     * Replace a char if it is defined on the current font.
     *
     * @param int $oldchar Integer code (unicode) of the character to replace.
     * @param int $newchar Integer code (unicode) of the new character.
     *
     * @return int the replaced char or the old char in case the new char i not defined
     */
    public function replaceChar($oldchar, $newchar)
    {
        if ($this->isCharDefined($newchar)) {
            // add the new char on the subset list
            $this->addSubsetChar($this->stack[$this->index]['key'], $newchar);
            // return the new character
            return $newchar;
        }
        // return the old char
        return $oldchar;
    }

    /**
     * Returns the font metrics associated to the input key.
     *
     * @param array $font Stack item
     *
     * @return array
     */
    protected function getFontMetric($font)
    {
        $mkey = md5(serialize($font));
        if (!empty($this->metric[$mkey])) {
            return $this->metric[$mkey];
        }

        $usize = ((float) $font['size'] / $this->kunit);
        $cratio = ($usize / 1000);
        $wratio = ($cratio * $font['stretching']); // horizontal ratio
        $data = $this->getFont($font['key']);

        // add this font in the stack wit metrics in internal units
        $this->metric[$mkey] = array(
            'out'          => sprintf('BT /F%d %F Tf ET', $data['i'], $font['size']), // PDF output string
            'key'          => $font['key'],
            'size'         => $font['size'],                                          // size in points
            'spacing'      => $font['spacing'],
            'stretching'   => $font['stretching'],
            'usize'        => $usize,                                                 // size in internal units
            'cratio'       => $cratio,                                                // conversion ratio
            'up'           => ($data['up'] * $cratio),
            'ut'           => ($data['ut'] * $cratio),
            'dw'           => ($data['dw'] * $cratio * $font['stretching']),
            'ascent'       => ($data['desc']['Ascent'] * $cratio),
            'descent'      => ($data['desc']['Descent'] * $cratio),
            'capheight'    => ($data['desc']['CapHeight'] * $cratio),
            'xheight'      => ($data['desc']['XHeight'] * $cratio),
            'avgwidth'     => ($data['desc']['AvgWidth'] * $cratio * $font['stretching']),
            'maxwidth'     => ($data['desc']['MaxWidth'] * $cratio * $font['stretching']),
            'missingwidth' => ($data['desc']['MissingWidth'] * $cratio * $font['stretching']),
            'cw'           => array(),
            'cbbox'        => array(),
        );
        $tbox = explode(' ', substr($data['desc']['FontBBox'], 1, -1));
        $this->metric[$mkey]['fbbox'] = array(
            ($tbox[0] * $wratio), // left
            ($tbox[1] * $cratio), // bottom
            ($tbox[2] * $wratio), // right
            ($tbox[3] * $cratio), // top
        );
        //left, bottom, right, and top edges
        foreach ($data['cw'] as $chr => $width) {
            $this->metric[$mkey]['cw'][$chr] = ($width * $wratio);
        }
        foreach ($data['cbbox'] as $chr => $val) {
            $this->metric[$mkey]['cbbox'][$chr] = array(
                ($val[0] * $wratio), // left
                ($val[1] * $cratio), // bottom
                ($val[2] * $wratio), // right
                ($val[3] * $cratio), // top
            );
        }

        return $this->metric[$mkey];
    }

    /**
     * Normalize the input size
     *
     * return float
     */
    protected function getInputSize($size = null)
    {
        if ($size === null) {
            if ($this->index >= 0) {
                // inherit the size of the last inserted font
                return $this->stack[$this->index]['size'];
            } else {
                return self::DEFAULT_SIZE;
            }
        }
        return max(0, (float) $size);
    }

    /**
     * Normalize the input spacing
     *
     * @param float  $spacing  Extra spacing between characters.
     *
     * return float
     */
    protected function getInputSpacing($spacing = null)
    {
        if ($spacing === null) {
            if ($this->index >= 0) {
                // inherit the size of the last inserted font
                return $this->stack[$this->index]['spacing'];
            } else {
                return 0;
            }
        }
        return ((float) $spacing);
    }

    /**
     * Normalize the input stretching
     *
     * @param float  $stretching Horizontal character stretching ratio.
     *
     * return float
     */
    protected function getInputStretching($stretching = null)
    {
        if ($stretching === null) {
            if ($this->index >= 0) {
                // inherit the size of the last inserted font
                return $this->stack[$this->index]['stretching'];
            } else {
                return 1;
            }
        }
        return ((float) $stretching);
    }

    /**
     * Return normalized font keys
     *
     * @param string $fontfamily Property string containing comma-separated font family names
     *
     * @return array
     */
    protected function getNormalizedFontKeys($fontfamily)
    {
        $keys = array();
        // remove spaces and symbols
        $fontfamily = preg_replace('/[^a-z0-9_\,]/', '', strtolower($fontfamily));
        // extract all font names
        $fontslist = preg_split('/[,]/', $fontfamily);
        // replacement patterns
        $pattern = array('/^serif|^cursive|^fantasy|^timesnewroman/', '/^sansserif/', '/^monospace/');
        $replacement = array('times', 'helvetica', 'courier');
        // find first valid font name
        foreach ($fontslist as $font) {
            // replace font variations
            $font = preg_replace('/regular$/', '', $font);
            $font = preg_replace('/italic$/', 'I', $font);
            $font = preg_replace('/oblique$/', 'I', $font);
            $font = preg_replace('/bold([I]?)$/', 'B\\1', $font);
            // replace common family names and core fonts
            $keys[] = preg_replace($pattern, $replacement, $font);
        }
        return $keys;
    }
}
