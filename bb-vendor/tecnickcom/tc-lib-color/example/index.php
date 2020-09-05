<?php
/**
 * index.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Color
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-color
 *
 * This file is part of tc-lib-color software library.
 */

// autoloader when using Composer
require ('../vendor/autoload.php');

// autoloader when using RPM or DEB package installation
//require ('/usr/share/php/Com/Tecnick/Color/autoload.php');

$colobj = new \Com\Tecnick\Color\Web;

$colmap = $colobj->getMap();

$tablerows = '';
$invtablerows = '';
foreach ($colmap as $name => $hex) {
    $rgbcolor = $colobj->getRgbObjFromHex($hex);
    $hslcolor = new \Com\Tecnick\Color\Model\Hsl($rgbcolor->toHslArray());
    $comp = $rgbcolor->getNormalizedArray(255);
    // web colors
    $tablerows .= '<tr>'
        .'<td style="background-color:'.$rgbcolor->getCssColor().';">&nbsp;</td>'
        .'<td>'.$name.'</td>'
        .'<td>'.$rgbcolor->getRgbHexColor().'</td>'
        .'<td style="text-align:right;">'.$comp['R'].'</td>'
        .'<td style="text-align:right;">'.$comp['G'].'</td>'
        .'<td style="text-align:right;">'.$comp['B'].'</td>'
        .'<td>'.$rgbcolor->getCssColor().'</td>'
        .'<td>'.$hslcolor->getCssColor().'</td>'
        .'<td>'.$rgbcolor->getJsPdfColor().'</td>'
        .'</tr>'."\n";
    // normalised inverted web colors
    $invcolor = clone $rgbcolor;
    $invcolor->invertColor();
    $invcolname = $colobj->getClosestWebColor($invcolor->toRgbArray());
    $invrgbcolor = $colobj->getRgbObjFromName($invcolname);
    $invtablerows .= '<tr>'
        .'<td style="text-align:right;">'.$name.'</td>'
        .'<td style="background-color:'.$rgbcolor->getCssColor().';">&nbsp;</td>'
        .'<td style="background-color:'.$invrgbcolor->getCssColor().';">&nbsp;</td>'
        .'<td>'.$invcolname.'</td>'
        .'</tr>'."\n";
}

echo "
<!DOCTYPE html>
<html>
    <head>
        <title>Usage example of tc-lib-color library</title>
        <meta charset=\"utf-8\">
        <style>
            body {font-family:Arial, Helvetica, sans-serif;}
            table {border: 1px solid black;font-family: \"Courier New\", Courier, monospace}
            th {border: 1px solid black;padding:4px;background-color:cornsilk;}
            td {border: 1px solid black;padding:4px;}
        </style>
    </head>

    <body>
        <h1>Usage example of tc-lib-color library</h1>
        <p>This is an usage example of <a href=\"https://github.com/tecnickcom/tc-lib-color\" title=\"tc-lib-color: PHP library to manipulate various color representations\">tc-lib-color</a> library.</p>
        <h2>Web Colors Table</h2>
        <table>
            <thead>
                <tr>
                    <th>COLOR</th>
                    <th>NAME</th>
                    <th>HEX</th>
                    <th>RED</th>
                    <th>GREEN</th>
                    <th>BLUE</th>
                    <th>CSS-RGBA</th>
                    <th>CSS-HSLA</th>
                    <th>PDF-JS</th>
                </tr>
            </thead>
            <tbody>
".$tablerows."
            </tbody>
        </table>
        <h2>Normalized Inverted Web Colors Table</h2>
        <table>
            <thead>
                <tr>
                    <th colspan=\"2\">A</th>
                    <th colspan=\"2\">B</th>
                </tr>
                <tr>
                    <th>NAME</th>
                    <th>COLOR</th>
                    <th>COLOR</th>
                    <th>NAME</th>
                </tr>
            </thead>
            <tbody>
".$invtablerows."
            </tbody>
        </table>
    </body>
</html>
";
