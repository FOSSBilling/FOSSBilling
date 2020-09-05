<?php
/**
 * Barcode.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Com\Tecnick\Barcode;

use \Com\Tecnick\Barcode\Exception as BarcodeException;

/**
 * Com\Tecnick\Barcode\Barcode
 *
 * Barcode Barcode class
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class Barcode
{
    /**
     * Array containing the map between the barcode type and correspondent class
     *
     * @var array
     */
    protected static $typeclass = array(
        'C128A'      => 'Linear\\CodeOneTwoEight\\A',        // CODE 128 A
        'C128B'      => 'Linear\\CodeOneTwoEight\\B',        // CODE 128 B
        'C128C'      => 'Linear\\CodeOneTwoEight\\C',        // CODE 128 C
        'C128'       => 'Linear\\CodeOneTwoEight',           // CODE 128
        'C39E+'      => 'Linear\\CodeThreeNineExtCheck',     // CODE 39 EXTENDED + CHECKSUM
        'C39E'       => 'Linear\\CodeThreeNineExt',          // CODE 39 EXTENDED
        'C39+'       => 'Linear\\CodeThreeNineCheck',        // CODE 39 + CHECKSUM
        'C39'        => 'Linear\\CodeThreeNine',             // CODE 39 - ANSI MH10.8M-1983 - USD-3 - 3 of 9.
        'C93'        => 'Linear\\CodeNineThree',             // CODE 93 - USS-93
        'CODABAR'    => 'Linear\\Codabar',                   // CODABAR
        'CODE11'     => 'Linear\\CodeOneOne',                // CODE 11
        'EAN13'      => 'Linear\\EanOneThree',               // EAN 13
        'EAN2'       => 'Linear\\EanTwo',                    // EAN 2-Digits UPC-Based Extension
        'EAN5'       => 'Linear\\EanFive',                   // EAN 5-Digits UPC-Based Extension
        'EAN8'       => 'Linear\\EanEight',                  // EAN 8
        'I25+'       => 'Linear\\InterleavedTwoOfFiveCheck', // Interleaved 2 of 5 + CHECKSUM
        'I25'        => 'Linear\\InterleavedTwoOfFive',      // Interleaved 2 of 5
        'IMB'        => 'Linear\\Imb',                       // IMB - Intelligent Mail Barcode - Onecode - USPS-B-3200
        'IMBPRE'     => 'Linear\\ImbPre',                    // IMB - Intelligent Mail Barcode pre-processed
        'KIX'        => 'Linear\\KlantIndex',                // KIX (Klant index - Customer index)
        'MSI+'       => 'Linear\\MsiCheck',                  // MSI + CHECKSUM (modulo 11)
        'MSI'        => 'Linear\\Msi',                       // MSI (Variation of Plessey code)
        'PHARMA2T'   => 'Linear\\PharmaTwoTracks',           // PHARMACODE TWO-TRACKS
        'PHARMA'     => 'Linear\\Pharma',                    // PHARMACODE
        'PLANET'     => 'Linear\\Planet',                    // PLANET
        'POSTNET'    => 'Linear\\Postnet',                   // POSTNET
        'RMS4CC'     => 'Linear\\RoyalMailFourCc',           // RMS4CC (Royal Mail 4-state Customer Bar Code)
        'S25+'       => 'Linear\\StandardTwoOfFiveCheck',    // Standard 2 of 5 + CHECKSUM
        'S25'        => 'Linear\\StandardTwoOfFive',         // Standard 2 of 5
        'UPCA'       => 'Linear\\UpcA',                      // UPC-A
        'UPCE'       => 'Linear\\UpcE',                      // UPC-E
        'DATAMATRIX' => 'Square\\Datamatrix',                // DATAMATRIX (ISO/IEC 16022)
        'PDF417'     => 'Square\\PdfFourOneSeven',           // PDF417 (ISO/IEC 15438:2006)
        'QRCODE'     => 'Square\\QrCode',                    // QR-CODE
        'LRAW'       => 'Linear\\Raw',                       // 1D RAW MODE (comma-separated rows of 01 strings)
        'SRAW'       => 'Square\\Raw',                       // 2D RAW MODE (comma-separated rows of 01 strings)
    );

    /**
     * Get the list of supported Barcode types
     *
     * @return array
     */
    public function getTypes()
    {
        return array_keys(self::$typeclass);
    }

    /**
     * Get the barcode object
     *
     * @param string $type    Barcode type
     * @param string $code    Barcode content
     * @param int    $width   Barcode width in user units (excluding padding).
     *                        A negative value indicates the multiplication factor for each column.
     * @param int    $height  Barcode height in user units (excluding padding).
     *                        A negative value indicates the multiplication factor for each row.
     * @param string $color   Foreground color in Web notation (color name, or hexadecimal code, or CSS syntax)
     * @param array  $padding Additional padding to add around the barcode (top, right, bottom, left) in user units.
     *                        A negative value indicates the multiplication factor for each row or column.
     *
     * @return Type
     *
     * @throws BarcodeException in case of error
     */
    public function getBarcodeObj(
        $type,
        $code,
        $width = -1,
        $height = -1,
        $color = 'black',
        $padding = array(0, 0, 0, 0)
    ) {
        // extract extra parameters (if any)
        $params = explode(',', $type);
        $type = array_shift($params);
        
        if (empty(self::$typeclass[$type])) {
            throw new BarcodeException('Unsupported barcode type: '.$type);
        }
        $bclass = '\\Com\\Tecnick\\Barcode\\Type\\'.self::$typeclass[$type];
        return new $bclass($code, $width, $height, $color, $params, $padding);
    }
}
