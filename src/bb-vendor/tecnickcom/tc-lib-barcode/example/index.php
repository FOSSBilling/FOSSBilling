<?php
/**
 * index.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2020 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

// autoloader when using Composer
require ('../vendor/autoload.php');

// autoloader when using RPM or DEB package installation
//require ('/usr/share/php/Com/Tecnick/Barcode/autoload.php');

// data to generate for each barcode type
$linear = array(
    'C128A'      => array('0123456789', 'CODE 128 A'),
    'C128B'      => array('0123456789', 'CODE 128 B'),
    'C128C'      => array('0123456789', 'CODE 128 C'),
    'C128'       => array('0123456789', 'CODE 128'),
    'C39E+'      => array('0123456789', 'CODE 39 EXTENDED + CHECKSUM'),
    'C39E'       => array('0123456789', 'CODE 39 EXTENDED'),
    'C39+'       => array('0123456789', 'CODE 39 + CHECKSUM'),
    'C39'        => array('0123456789', 'CODE 39 - ANSI MH10.8M-1983 - USD-3 - 3 of 9'),
    'C93'        => array('0123456789', 'CODE 93 - USS-93'),
    'CODABAR'    => array('0123456789', 'CODABAR'),
    'CODE11'     => array('0123456789', 'CODE 11'),
    'EAN13'      => array('0123456789', 'EAN 13'),
    'EAN2'       => array('12',         'EAN 2-Digits UPC-Based Extension'),
    'EAN5'       => array('12345',      'EAN 5-Digits UPC-Based Extension'),
    'EAN8'       => array('1234567',    'EAN 8'),
    'I25+'       => array('0123456789', 'Interleaved 2 of 5 + CHECKSUM'),
    'I25'        => array('0123456789', 'Interleaved 2 of 5'),
    'IMB'        => array('01234567094987654321-01234567891', 'IMB - Intelligent Mail Barcode - Onecode - USPS-B-3200'),
    'IMBPRE'     => array('AADTFFDFTDADTAADAATFDTDDAAADDTDTTDAFADADDDTFFFDDTTTADFAAADFTDAADA', 'IMB pre-processed'),
    'KIX'        => array('0123456789', 'KIX (Klant index - Customer index)'),
    'MSI+'       => array('0123456789', 'MSI + CHECKSUM (modulo 11)'),
    'MSI'        => array('0123456789', 'MSI (Variation of Plessey code)'),
    'PHARMA2T'   => array('0123456789', 'PHARMACODE TWO-TRACKS'),
    'PHARMA'     => array('0123456789', 'PHARMACODE'),
    'PLANET'     => array('0123456789', 'PLANET'),
    'POSTNET'    => array('0123456789', 'POSTNET'),
    'RMS4CC'     => array('0123456789', 'RMS4CC (Royal Mail 4-state Customer Bar Code)'),
    'S25+'       => array('0123456789', 'Standard 2 of 5 + CHECKSUM'),
    'S25'        => array('0123456789', 'Standard 2 of 5'),
    'UPCA'       => array('72527273070', 'UPC-A'),
    'UPCE'       => array('725277', 'UPC-E'),
);

$square = array(
    'LRAW'             => array('0101010101', '1D RAW MODE (comma-separated rows of 01 strings)'),
    'SRAW'             => array('0101,1010',  '2D RAW MODE (comma-separated rows of 01 strings)'),
    'PDF417'           => array('0123456789', 'PDF417 (ISO/IEC 15438:2006)'),
    'QRCODE'           => array('0123456789', 'QR-CODE'),
    'QRCODE,H,ST,0,0'  => array('abcdefghijklmnopqrstuvwxy0123456789', 'QR-CODE WITH PARAMETERS'),
    'DATAMATRIX'       => array('0123456789', 'DATAMATRIX (ISO/IEC 16022) SQUARE'),
    'DATAMATRIX,R'     => array('0123456789012345678901234567890123456789', 'DATAMATRIX Rectangular (ISO/IEC 16022) RECTANGULAR'),
    'DATAMATRIX,S,GS1' => array(chr(232).'01095011010209171719050810ABCD1234'.chr(232).'2110', 'GS1 DATAMATRIX (ISO/IEC 16022) SQUARE GS1'),
    'DATAMATRIX,R,GS1' => array(chr(232).'01095011010209171719050810ABCD1234'.chr(232).'2110', 'GS1 DATAMATRIX (ISO/IEC 16022) RECTANGULAR GS1'),
);

$barcode = new \Com\Tecnick\Barcode\Barcode();

$examples = '<h3>Linear</h3>'."\n";
foreach ($linear as $type => $code) {
    $bobj = $barcode->getBarcodeObj($type, $code[0], -3, -30, 'black', array(0, 0, 0, 0));
    $examples .= '<h4>[<span>'.$type.'</span>] '.$code[1].'</h4><p style="font-family:monospace;">'.$bobj->getHtmlDiv().'</p>'."\n";
}

$examples .= '<h3>Square</h3>'."\n";
foreach ($square as $type => $code) {
    $bobj = $barcode->getBarcodeObj($type, $code[0], -4, -4, 'black', array(0, 0, 0, 0));
    $examples .= '<h4>[<span>'.$type.'</span>] '.$code[1].'</h4><p style="font-family:monospace;">'.$bobj->getHtmlDiv().'</p>'."\n";
}

$bobj = $barcode->getBarcodeObj('QRCODE,H', 'https://tecnick.com', -4, -4, 'black', array(-2, -2, -2, -2))->setBackgroundColor('#f0f0f0');

echo "
<!DOCTYPE html>
<html>
    <head>
        <title>Usage example of tc-lib-barcode library</title>
        <meta charset=\"utf-8\">
        <style>
            body {font-family:Arial, Helvetica, sans-serif;margin:30px;}
            table {border: 1px solid black;}
            th {border: 1px solid black;padding:4px;background-barcode:cornsilk;}
            td {border: 1px solid black;padding:4px;}
            h3 {color:darkblue;}
            h4 {color:darkgreen;}
            h4 span  {color:firebrick;}
        </style>
    </head>
    <body>
        <h1>Usage example of tc-lib-barcode library</h1>
        <p>This is an usage example of <a href=\"https://github.com/tecnickcom/tc-lib-barcode\" title=\"tc-lib-barcode: PHP library to generate linear and bidimensional barcodes\">tc-lib-barcode</a> library.</p>
        <h2>Output Formats</h2>
        <h3>PNG Image</h3>
        <p><img alt=\"Embedded Image\" src=\"data:image/png;base64,".base64_encode($bobj->getPngData())."\" /></p>
        <h3>SVG Image</h3>
        <p style=\"font-family:monospace;\">".$bobj->getSvgCode()."</p>
        <h3>HTML DIV</h3>
        <p style=\"font-family:monospace;\">".$bobj->getHtmlDiv()."</p>
        <h3>Unicode String</h3>
        <pre style=\"font-family:monospace;line-height:0.61em;font-size:6px;\">".$bobj->getGrid(json_decode('"\u00A0"'), json_decode('"\u2584"'))."</pre>
        <h3>Binary String</h3>
        <pre style=\"font-family:monospace;\">".$bobj->getGrid()."</pre>
        <h2>Barcode Types</h2>
        ".$examples."
    </body>
</html>
";
