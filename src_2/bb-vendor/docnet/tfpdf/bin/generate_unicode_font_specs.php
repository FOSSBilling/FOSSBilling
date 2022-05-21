<?php
/**
 * generate_unicode_font_specs.php
 *
 * @author David Wilcock <dwilcock@doc-net.com>
 * @copyright Venditan Limited 2016
 */

require_once __DIR__ . '/../vendor/autoload.php';

$str_search = __DIR__ . "/../src/font/unifont/*.ttf";

$arr_fonts = glob($str_search);

$int_generated = 0;
foreach ($arr_fonts as $str_font_filename) {
    $str_font_filename = basename($str_font_filename);
    preg_match("/^(.*)\.ttf$/", $str_font_filename, $arr_matches);
    if (count($arr_matches) > 1) {

        //$str_font_family = str_replace("-", "", $arr_matches[1]);
        $arr_bits = explode("-", $arr_matches[1]);
        $str_font_family = reset($arr_bits);
        $str_style = "";
        if (strpos($arr_matches[1], "Bold") !== FALSE) {
            $str_style .= "B";
        }
        if (strpos($arr_matches[1], "Italic") !== FALSE || strpos($arr_matches[1], "Oblique") !== FALSE) {
            $str_style .= "I";
        }
        $str_font_ttf = $arr_matches[0];

        $obj_pdf = new \tFPDF\PDF();
        $obj_pdf->AddPage();
        $obj_pdf->AddFont($str_font_family, $str_style, $str_font_filename, true);
        $obj_pdf->SetTextColor(0,0,0);
        $obj_pdf->SetXY(5, 5);
        $obj_pdf->SetFont($str_font_family, $str_style, 15);
        $str_extended_ascii = 'åàõê';
        $obj_pdf->MultiCell('100', 2, $str_extended_ascii);
        echo "Generated files for " . $str_font_family;
        if ($str_style != '') {
            echo "/" . $str_style;
        }
        echo "\n";
        $int_generated++;
        $str_test_dir = "test_pdfs/";
        if (!is_dir($str_test_dir)) {
            mkdir($str_test_dir, 0755);
        }
        $str_test_file = $str_test_dir . $str_font_family . ($str_style == '' ? '' : '-' . $str_style) . ".pdf";
        file_put_contents($str_test_file, $obj_pdf->output());
        echo "Written " . $str_test_file . "\n";
    }
}
echo "Generated " . $int_generated . " font spec files\n";