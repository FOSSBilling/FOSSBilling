#!/usr/bin/env php
<?php
/**
 * bulk_convert.php
 *
 * @since       2015-11-30
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 *
 * Command-line tool to convert fonts data for the tc-lib-pdf-font library in bulk.
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

bulk_convert - Command-line tool to convert fonts data for the tc-lib-pdf-font library.

Usage:
    bulk_convert.php [ options ]

Options:

    -o, --outpath
        Output path for generated font files (must be writeable by the
        web server). Leave empty for default font folder.

    -h, --help
        Display this help and exit.

EOD;
    fwrite(STDOUT, $help);
    exit(0);
}

// initialize the array of options
$options = array('outpath' => dirname(__DIR__).'/target/fonts/');

// short input options
$sopt = 'o:';

// long input options
$lopt = array('outpath:');

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
        case 'h':
        case 'help':
        default:
            showHelp();
            break;
    }
}

// check input values
if (!is_dir($options['outpath'])) {
    mkdir($options['outpath'], 0755, true);
}
if (!is_writable($options['outpath'])) {
    fwrite(STDERR, 'ERROR: Can\'t write to '.$options['outpath']."\n\n");
    exit(2);
}

$ttfdir = __DIR__.'/vendor/font/';
if (!is_dir($ttfdir)) {
    fwrite(STDERR, 'ERROR: The '.$ttfdir.' directory is empty, please execute \'make build\' before this command.'."\n\n");
    exit(3);
}

fwrite(STDOUT, "\n".'>>> Converting fonts:'."\n".'*** Output directory set to '.$options['outpath']."\n");

// count conversions
$convert_errors = 0;
$convert_success = 0;

require_once (dirname(__DIR__).'/vendor/autoload.php');

$fontdir = array_diff(scandir($ttfdir), array('.', '..', '.git'));

// URL of websites containing the font sources
$font_url = array(
    'cid0'     => 'http://unifoundry.com/unifont.html',
    'core'     => 'https://partners.adobe.com/public/developer/en/pdf/Core14_AFMs.zip',
    'dejavu'   => 'http://sourceforge.net/projects/dejavu/files/dejavu/2.35/dejavu-fonts-ttf-2.35.zip',
    'freefont' => 'https://ftp.gnu.org/gnu/freefont/freefont-ttf-20120503.zip',
    'noto'     => 'https://www.google.com/get/noto',
    'notocjk'  => 'https://www.google.com/get/noto',
    'pdfa'     => 'https://github.com/tecnickcom/tc-font-pdfa',
    'unifont'  => 'http://unifoundry.com/unifont.html',
);

foreach ($fontdir as $dir) {
    if (!is_dir($ttfdir.$dir)) {
        continue;
    }
    // search font files in sub directories
    $all_files  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ttfdir.$dir));
    $fonts = iterator_to_array(new RegexIterator($all_files, '/\.ttf$/'));
    $fonts = array_merge($fonts, iterator_to_array(new RegexIterator($all_files, '/\.pfb$/')));
    $fonts = array_merge($fonts, iterator_to_array(new RegexIterator($all_files, '/\.otf$/')));
    if (empty($fonts)) {
        $fonts = iterator_to_array(new RegexIterator($all_files, '/\.afm$/'));
    }
    if (empty($fonts)) {
        continue;
    }

    // build output path directory
    $outdir = $options['outpath'].$dir.'/';
    if (!is_dir($outdir)) {
        mkdir($outdir, 0755, true);
    }
    copy($ttfdir.$dir.'/LICENSE', $outdir.'LICENSE');

    // generate a README file
    $readme = '# '.$dir.' font files for tc-lib-pdf-font'."\n\n"
        .'This folder contains font files and/or font data extracted from:'."\n"
        .$font_url[$dir]."\n"
        .'using the "bulk_convert.php" utility in https://github.com/tecnickcom/tc-font-pdf-font'."\n\n"
        .'The original files (if present) have been renamed and compressed using the ZLIB data format (.z files).'."\n"
        .'The font files are subject to the conditions stated in the LICENSE file.'."\n"
        .'For further information please consult the original documentation at the link above.'."\n";
    file_put_contents($outdir.'README', $readme);

    foreach ($fonts as $font) {
        if (substr($font, -4) == '.otf') {
            // OTF fonts are not supported but we can try to convert them to TTF using FontForge
            system('fontforge -script otf2ttf.ff '.escapeshellcmd($font), $err);
            if ($err != 0) {
                fwrite(STDERR, "\033[31m".'Unable to convert: '.$font."\033[m");
                continue;
            }
            $font = substr($font, 0, -4).'.ttf';
        }
        
        $type = null;
        $encoding = null;
        if ($dir == 'cid0') {
            $type = strtoupper(basename($font, '.ttf'));
        } elseif (($dir == 'core') || ($dir == 'pdfa')) {
            if (strpos($font, 'Symbol') !== false) {
                $encoding = 'symbol';
            } elseif (strpos($font, 'ZapfDingbats') === false) {
                $encoding = 'cp1252';
            }
        }
        try {
            $import = new \Com\Tecnick\Pdf\Font\Import(
                realpath($font),
                $outdir,
                $type,
                $encoding
            );
            $fontname = $import->getFontName();
            fwrite(STDOUT, "\033[32m".'+++ OK   : '.$font.' added as '.$fontname."\033[m\n");
            ++$convert_success;
        } catch (\Exception $exc) {
            ++$convert_errors;
            fwrite(STDERR, "\033[31m".'--- ERROR: can\'t add '.$font."\n           ".$exc->getMessage()."\033[m\n");
        }
    }
}

$endmsg = '>>> PROCESS COMPLETED: '.$convert_success.' CONVERTED FONT(S), '.$convert_errors.' ERROR(S)!'."\n\n";

if ($convert_errors > 0) {
    fwrite(STDERR, "\033[31m".$endmsg.'ERROR'."\033[m");
    exit(4);
}

fwrite(STDOUT, "\033[32m".$endmsg."\033[m");
exit(0);

