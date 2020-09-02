<?php

// Définition facultative du répertoire des polices systèmes
// Sinon tFPDF utilise le répertoire [chemin vers tFPDF]/font/unifont/
// define("_SYSTEM_TTFONTS", "C:/Windows/Fonts/");

require('tfpdf.php');

$pdf = new tFPDF();
$pdf->AddPage();

// Ajoute une police Unicode (utilise UTF-8)
$pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
$pdf->SetFont('DejaVu','',14);

// Charge une chaîne UTF-8 à partir d'un fichier
$txt = file_get_contents('HelloWorld.txt');
$pdf->Write(8,$txt);

// Sélectionne une police standard (utilise windows-1252)
$pdf->SetFont('Arial','',14);
$pdf->Ln(10);
$pdf->Write(5,"La taille de ce PDF n'est que de 12 ko.");

$pdf->Output();
?>
