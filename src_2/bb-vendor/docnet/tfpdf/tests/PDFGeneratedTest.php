<?php

use PHPUnit\Framework\TestCase;

define('FPDF_FONT_WRITE_PATH', __DIR__ . '/../src/font/');

class PDFGeneratedTest extends TestCase
{
    public function testFileIsGenerated()
    {
        $pdfLibrary = new tFPDF\PDF();

        $pdfLibrary->AddPage();

        $pdfLibrary->AddFont('DejaVuSansCondensed', '', 'DejaVuSansCondensed.ttf', true);
        $pdfLibrary->SetFont('DejaVuSansCondensed', '', 14);

        $txt = file_get_contents(__DIR__ . '/test_data/HelloWorld.txt');
        $pdfLibrary->Write(8, $txt);

        $pdfLibrary->SetFont('Arial', '', 14);
        $pdfLibrary->Ln(10);
        $pdfLibrary->Write(5, "La taille de ce PDF n'est que de 12 ko.");

        $pdfLibrary->SetFont('Courier', '', 14);
        $pdfLibrary->Ln(10);
        $pdfLibrary->SetTextColor(255, 0, 0);
        $pdfLibrary->Write(5, "Hello Red Courier World");
        $pdfLibrary->Ln(10);
        $pdfLibrary->SetTextColor(122.5);
        $pdfLibrary->Write(5, "Hello Gray Courier World");
        $pdfLibrary->SetFont('Courier', 'U', 14);
        $pdfLibrary->Ln(10);
        $pdfLibrary->SetTextColor();
        $pdfLibrary->Write(5, "Hello Underscored Courier World");

        $pdfLibrary->Ln(10);

        // Set draw color example
        $pdfLibrary->SetLineWidth(2);
        $pdfLibrary->SetDrawColor(122.5);
        $pdfLibrary->Line(20, $pdfLibrary->GetY(), 200, $pdfLibrary->GetY());
        $pdfLibrary->Ln(10);

        // Set fill color example
        $pdfLibrary->SetFillColor(122.5);
        $pdfLibrary->Rect(20, $pdfLibrary->GetY(), 180, 20, 'F');
        $pdfLibrary->Ln(30);

        // Set text color example
        $pdfLibrary->SetFont('Courier', '', 14);
        $pdfLibrary->SetTextColor(122.5);
        $pdfLibrary->Text(20, $pdfLibrary->GetY(), 'Test text');

        $file = $pdfLibrary->output();

        if (empty($file)) {
            static::fail("Empty PDF library output");
        }

        $file_name = __DIR__ . '/test_data/output.pdf';

        if (file_exists($file_name)) {
            unlink($file_name);
        }
        file_put_contents($file_name, $file);

        if (!file_exists($file_name)) {
            static::fail("PDF {$file_name} file does not exist");
        }
    }
}
