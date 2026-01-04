<?php
/**
 * PDF Generator Utility
 * Bietet Funktionen zum Konvertieren von HTML in PDF und zum Herunterladen
 * 
 * Dieses Skript nutzt die dompdf-Bibliothek
 */

// Autolader für dompdf
require_once 'vendor/dompdf/autoload.inc.php';

// Benutzung der dompdf-Namespace
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Konvertiert HTML in eine PDF-Datei und sendet sie als Download
 * 
 * @param string $html Der HTML-Inhalt, der in PDF konvertiert werden soll
 * @param string $filename Der Dateiname für die PDF (ohne Erweiterung)
 * @param string $paperSize Papiergröße für das PDF (A4, letter, etc.)
 * @param string $orientation Seitenausrichtung ('portrait' oder 'landscape')
 */
function generatePdfFromHtml($html, $filename = 'document', $paperSize = 'A4', $orientation = 'portrait') {
    // Initialisieren der Optionen
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    // Initialisieren von dompdf
    $dompdf = new Dompdf($options);
    
    // HTML-Inhalt laden
    $dompdf->loadHtml($html);
    
    // Papiergröße und -ausrichtung festlegen
    $dompdf->setPaper($paperSize, $orientation);
    
    // HTML in PDF rendern
    $dompdf->render();
    
    // PDF an den Browser senden
    $dompdf->stream(
        $filename . ".pdf",
        array(
            "Attachment" => true   // true = als Download, false = im Browser anzeigen
        )
    );
    
    exit(0);
}

/**
 * Konvertiert HTML in eine PDF-Datei und gibt den PDF-Inhalt zurück (ohne Download)
 * 
 * @param string $html Der HTML-Inhalt, der in PDF konvertiert werden soll
 * @param string $paperSize Papiergröße für das PDF (A4, letter, etc.)
 * @param string $orientation Seitenausrichtung ('portrait' oder 'landscape')
 * @return string Der PDF-Inhalt als String
 */
function getPdfContentFromHtml($html, $paperSize = 'A4', $orientation = 'portrait') {
    // Initialisieren der Optionen
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    // Initialisieren von dompdf
    $dompdf = new Dompdf($options);
    
    // HTML-Inhalt laden
    $dompdf->loadHtml($html);
    
    // Papiergröße und -ausrichtung festlegen
    $dompdf->setPaper($paperSize, $orientation);
    
    // HTML in PDF rendern
    $dompdf->render();
    
    // PDF-Inhalt zurückgeben
    return $dompdf->output();
}