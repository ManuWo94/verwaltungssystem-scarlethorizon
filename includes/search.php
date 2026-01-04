<?php
/**
 * Suchfunktionen für die Fraktionsverwaltung
 */

/**
 * Sucht nach Fraktionen basierend auf einem Suchbegriff
 * 
 * @param string $searchTerm Der Suchbegriff
 * @return array
 */
function searchFraktionen($searchTerm) {
    $fraktionen = loadFraktionen();
    $results = [];
    
    if (empty($searchTerm)) {
        return $fraktionen;
    }
    
    $searchTerm = strtolower($searchTerm);
    
    foreach ($fraktionen as $fraktion) {
        if (strpos(strtolower($fraktion['name']), $searchTerm) !== false || 
            strpos(strtolower($fraktion['beschreibung']), $searchTerm) !== false) {
            $results[] = $fraktion;
        }
    }
    
    return $results;
}

/**
 * Sucht nach Items basierend auf einem Suchbegriff
 * 
 * @param string $searchTerm Der Suchbegriff
 * @return array
 */
function searchItems($searchTerm) {
    $items = loadItems();
    $results = [];
    
    if (empty($searchTerm)) {
        return $items;
    }
    
    $searchTerm = strtolower($searchTerm);
    
    foreach ($items as $item) {
        if (strpos(strtolower($item['name']), $searchTerm) !== false || 
            strpos(strtolower($item['beschreibung']), $searchTerm) !== false) {
            $results[] = $item;
        }
    }
    
    return $results;
}

/**
 * Sucht nach Materialien basierend auf einem Suchbegriff
 * 
 * @param string $searchTerm Der Suchbegriff
 * @return array
 */
function searchMaterialien($searchTerm) {
    $materialien = loadMaterialien();
    $results = [];
    
    if (empty($searchTerm)) {
        return $materialien;
    }
    
    $searchTerm = strtolower($searchTerm);
    
    foreach ($materialien as $material) {
        if (strpos(strtolower($material['name']), $searchTerm) !== false || 
            strpos(strtolower($material['beschreibung']), $searchTerm) !== false) {
            $results[] = $material;
        }
    }
    
    return $results;
}

/**
 * Sucht nach Produktionsrouten basierend auf einem Suchbegriff
 * 
 * @param string $searchTerm Der Suchbegriff
 * @param array $routen Die zu durchsuchenden Routen
 * @return array
 */
function searchProduktionsrouten($searchTerm, $routen) {
    $results = [];
    
    if (empty($searchTerm)) {
        return $routen;
    }
    
    $searchTerm = strtolower($searchTerm);
    
    foreach ($routen as $route) {
        if (strpos(strtolower($route['name']), $searchTerm) !== false || 
            strpos(strtolower($route['fraktion']), $searchTerm) !== false) {
            $results[] = $route;
        } else {
            // Auch in Zutaten suchen
            foreach ($route['zutaten'] as $zutat) {
                if (strpos(strtolower($zutat['name']), $searchTerm) !== false || 
                    strpos(strtolower($zutat['fraktion']), $searchTerm) !== false) {
                    $results[] = $route;
                    break;
                }
            }
        }
    }
    
    return $results;
}

/**
 * Liefert Vorschläge für Materialien und Items für die Autovervollständigung
 * 
 * @param string $term Der eingegebene Suchbegriff
 * @return array
 */
function getAutocompleteItems($term) {
    $suggestions = [];
    $materialien = loadMaterialien();
    $items = loadItems();
    
    // Materialien durchsuchen
    foreach ($materialien as $material) {
        if (stripos($material['name'], $term) !== false) {
            $suggestions[] = [
                'id' => 'material_' . $material['id'],
                'text' => $material['name'],
                'type' => 'material'
            ];
        }
    }
    
    // Items durchsuchen
    foreach ($items as $item) {
        if (stripos($item['name'], $term) !== false) {
            $suggestions[] = [
                'id' => 'item_' . $item['id'],
                'text' => $item['name'],
                'type' => 'item'
            ];
        }
    }
    
    return $suggestions;
}

/**
 * AJAX-Handler für die Autovervollständigung
 * 
 * Kann aufgerufen werden mit: autocomplete.php?term=Suchbegriff
 */
if (isset($_GET['term'])) {
    require_once '../config/config.php';
    require_once 'functions.php';
    require_once 'data-handler.php';
    
    $term = trim($_GET['term']);
    $suggestions = getAutocompleteItems($term);
    
    // JSON-Header setzen und Ergebnis zurückgeben
    header('Content-Type: application/json');
    echo json_encode(['results' => $suggestions]);
    exit;
}
