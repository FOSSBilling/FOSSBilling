#!/usr/bin/env php
<?php
/**
 * convert.php
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
 *
 * Command-line tool to convert fonts data for the tc-lib-pdf-font library.
 */

if (php_sapi_name() != 'cli') {
    fwrite(STDERR, 'You need to run this command from console.'."\n");
    exit(1);
}

/**
 * Display help guide for this command.
 */
function showHelp()
{
    $help = <<<EOD

convert - Command-line tool to convert fonts data for the tc-lib-pdf-font library.

Usage:
    convert.php [ options ] -i fontfile[,fontfile]...

Options:

    -o, --outpath
        Output path for generated font files (must be writeable by the
        web server). Leave empty for default font folder.

    -t, --type
        Font type. Leave empty for autodetect mode.
        Valid values are:
            Core
            TrueTypeUnicode
            TrueType
            Type1
            CID0JP = CID-0 Japanese
            CID0KR = CID-0 Korean
            CID0CS = CID-0 Chinese Simplified
            CID0CT = CID-0 Chinese Traditional

    -e, --encoding
        Name of the encoding table to use.
        Leave empty for default mode.
        Omit this parameter for TrueTypeUnicode and symbolic fonts
        like Symbol or ZapfDingBats.

    -f, --flags
        Unsigned 32-bit integer containing flags specifying various
        characteristics of the font (see PDF32000:2008 - 9.8.2 Font
        Descriptor Flags):
            +1 for fixed font;
            +4 for symbol;
            +32 for non-symbol;
            +64 for italic.
        Fixed and Italic mode are generally autodetected so you have
        to set it to:
            4 = symbolic font;
            32 = non-symbolic font (default).

    -p, --platform_id
        Platform ID for CMAP table to extract (when building a Unicode
        font for Windows this value should be 3, for Macintosh should
        be 1).

    -n, --encoding_id
        Encoding ID for CMAP table to extract (when building a Unicode
        font for Windows this value should be 1, for Macintosh should
        be 0).
        When Platform ID is 3, legal values for Encoding ID are:
             0 = Symbol,
             1 = Unicode,
             2 = ShiftJIS,
             3 = PRC,
             4 = Big5,
             5 = Wansung,
             6 = Johab,
             7 = Reserved,
             8 = Reserved,
             9 = Reserved,
            10 = UCS-4.

    -l, --linked
        Link to system font instead of copying the font data (not
        transportable).
        Note: this feature is unsupported by Type1 fonts.

    -i, --fonts
        Comma-separated list of input font files.

    -h, --help
        Display this help and exit.

Examples:

    ./convert.php --outpath=/tmp/ --type=Type1 --encoding=cp1252 --flags=97 --encoding_id=1 \
    --fonts=/tmp/pdfa/pdfacourieri.pfb,/tmp/pdfa/pdfacourierbi.pfb

    ./convert.php --outpath=/tmp/ --type=TrueTypeUnicode --flags=32 --encoding_id=1 \
    --fonts=/tmp/freefont-20120503/FreeSans.ttf

    ./convert.php --outpath=/tmp/ --type=TrueTypeUnicode --flags=97 --encoding_id=1 \
    --fonts=/tmp/dejavu-fonts-ttf-2.35/ttf/DejaVuSansMono-BoldOblique.ttf


EOD;
    fwrite(STDOUT, $help);
    exit(0);
}

// remove the name of the executing script
array_shift($argv);

// no options chosen, display help
if (empty($argv)) {
    showHelp();
}

// initialize the array of options
$options = array(
    'outpath'     => './',
    'type'        => '',
    'encoding'    => '',
    'flags'       => 32,
    'platform_id' => 3,
    'encoding_id' => 1,
    'linked'        => false
);

// short input options
$sopt = 't:e:f:o:p:n:li:h';

// long input options
$lopt = array(
    'outpath:',
    'type:',
    'encoding:',
    'flags:',
    'platform_id:',
    'encoding_id:',
    'linked',
    'fonts:',
    'help'
);

// parse input options
$inopt = getopt($sopt, $lopt);

// import options (with some sanitization)
foreach ($inopt as $opt => $val) {
    switch ($opt) {
        case 'o':
        case 'outpath':
            $options['outpath'] = realpath($val);
            if (substr($options['outpath'], -1) != '/') {
                $options['outpath'] .= '/';
            }
            break;
        case 't':
        case 'type':
            if (in_array($val, array('TrueTypeUnicode', 'TrueType', 'Type1', 'CID0JP', 'CID0KR', 'CID0CS', 'CID0CT'))) {
                $options['type'] = $val;
            }
            break;
        case 'e':
        case 'encoding':
            $options['encoding'] = $val;
            break;
        case 'f':
        case 'flags':
            $options['flags'] = intval($val);
            break;
        case 'p':
        case 'platform_id':
            $options['platform_id'] = min(max(1, intval($val)), 3);
            break;
        case 'n':
        case 'encoding_id':
            $options['encoding_id'] = min(max(0, intval($val)), 10);
            break;
        case 'l':
        case 'linked':
            $options['linked'] = true;
            break;
        case 'i':
        case 'fonts':
            $options['fonts'] = explode(',', $val);
            break;
        case 'h':
        case 'help':
        default:
            showHelp();
            break;
    }
}

// check input values

if (!is_dir($options['outpath']) || !is_writable($options['outpath'])) {
    fwrite(STDERR, 'ERROR: Can\'t write to '.$options['outpath']."\n\n");
    exit(2);
}

if (empty($options['fonts'])) {
    fwrite(STDERR, 'ERROR: missing input fonts (try --help for usage)'."\n\n");
    exit(3);
}

fwrite(STDOUT, "\n".'>>> Converting fonts:'."\n".'*** Output directory set to '.$options['outpath']."\n");

// count conversions
$convert_errors = 0;
$convert_success = 0;

require_once (dirname(dirname(__DIR__)).'/vendor/autoload.php');

foreach ($options['fonts'] as $font) {
    try {
        $import = new \Com\Tecnick\Pdf\Font\Import(
            realpath($font),
            $options['outpath'],
            $options['type'],
            $options['encoding'],
            $options['flags'],
            $options['platform_id'],
            $options['encoding_id'],
            $options['linked']
        );
        $fontname = $import->getFontName();
        fwrite(STDOUT, "\033[32m".'+++ OK   : '.$font.' added as '.$fontname."\033[m\n");
        ++$convert_success;
    } catch (\Exception $exc) {
        ++$convert_errors;
        fwrite(STDERR, "\033[31m".'--- ERROR: can\'t add '.$font."\n           ".$exc->getMessage()."\033[m\n");
    }
}

$endmsg = '>>> PROCESS COMPLETED: '.$convert_success.' CONVERTED FONT(S), '.$convert_errors.' ERROR(S)!'."\n\n";

if ($convert_errors > 0) {
    fwrite(STDERR, "\033[31m".$endmsg.'ERROR'."\033[m");
    exit(4);
}

fwrite(STDOUT, "\033[32m".$endmsg."\033[m");
exit(0);
