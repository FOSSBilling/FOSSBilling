<?php
/**
 * test_unicode.php
 *
 * @author David Wilcock <dwilcock@doc-net.com>
 * @copyright Venditan Limited 2016
 */

require_once __DIR__ . '/../vendor/autoload.php';

$obj_pdf = new \tFPDF\PDF();
$obj_pdf->AddPage();
$obj_pdf->AddFont('DejaVuSans', '', 'DejaVuSans.ttf', true);
$obj_pdf->SetFont('DejaVuSans');

$obj_pdf->SetTextColor(0,0,0);
$obj_pdf->Cell(100, 2, 'Hyggelig å møte deg');

$str_file = sys_get_temp_dir() . '/tfpdf_unicode_test.pdf';
file_put_contents($str_file, $obj_pdf->output());

echo "Written file " . $str_file . PHP_EOL;