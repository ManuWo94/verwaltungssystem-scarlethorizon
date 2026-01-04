<?php
/**
 * AJAX-Handler für asynchrone Anfragen
 */

// Initialisiere die Session
session_start();

require_once '../config/config.php';
require_once 'functions.php';
require_once 'data-handler.php';

// Prüfe, ob der Benutzer angemeldet ist
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

// Setze Content-Type auf JSON
header('Content-Type: application/json');

// Verarbeite verschiedene AJAX-Aktionen
$action = isset($_GET['action']) ? $_GET['action'] : '';
$response = ['success' => false, 'message' => 'Unbekannte Aktion'];

switch ($action) {
    // Hole alle Items einer Fraktion
    case 'get_fraktion_items':
        if (isset($_GET['id'])) {
            $fraktionId = $_GET['id'];
            $items = getItemsForFraktion($fraktionId);
            $alleMaterialien = loadMaterialien();
            $alleItems = loadItems();
            
            // Bereite die Daten für die Antwort vor
            $aufbereiteteItems = [];
            foreach ($items as $item) {
                $aufbereitetesItem = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'beschreibung' => $item['beschreibung'],
                    'rezept' => []
                ];
                
                // Falls ein Rezept existiert, bereite die Bestandteile auf
                if (isset($item['rezept']) && is_array($item['rezept'])) {
                    foreach ($item['rezept'] as $bestandteil) {
                        $typ = isset($bestandteil['typ']) ? $bestandteil['typ'] : '';
                        $id = isset($bestandteil['id']) ? $bestandteil['id'] : '';
                        $menge = isset($bestandteil['menge']) ? $bestandteil['menge'] : 1;
                        $name = 'Unbekannt';
                        
                        if ($typ === 'material') {
                            foreach ($alleMaterialien as $material) {
                                if ($material['id'] === $id) {
                                    $name = $material['name'];
                                    break;
                                }
                            }
                        } elseif ($typ === 'item') {
                            foreach ($alleItems as $innerItem) {
                                if ($innerItem['id'] === $id) {
                                    $name = $innerItem['name'];
                                    break;
                                }
                            }
                        }
                        
                        $aufbereitetesItem['rezept'][] = [
                            'id' => $id,
                            'typ' => $typ,
                            'name' => $name,
                            'menge' => $menge
                        ];
                    }
                }
                
                $aufbereiteteItems[] = $aufbereitetesItem;
            }
            
            // Finde Fraktionen, die Items dieser Fraktion konsumieren
            $fraktionen = loadFraktionen();
            $consumers = [];
            
            // Prüfe für jedes Item, ob es in anderen Rezepten verwendet wird
            foreach ($aufbereiteteItems as $item) {
                $itemConsumers = [];
                
                foreach ($alleItems as $otherItem) {
                    // Prüfe neue Datenstruktur mit mehreren Fraktionen
                    $otherItemFraktionen = [];
                    if (isset($otherItem['fraktionen']) && is_array($otherItem['fraktionen'])) {
                        $otherItemFraktionen = $otherItem['fraktionen'];
                    } 
                    // Fallback auf altes Format
                    elseif (isset($otherItem['fraktion_id'])) {
                        $otherItemFraktionen = [$otherItem['fraktion_id']];
                    }
                    
                    $isOtherFraktionItem = !in_array($fraktionId, $otherItemFraktionen);
                    
                    if ($isOtherFraktionItem && isset($otherItem['rezept']) && is_array($otherItem['rezept'])) {
                        foreach ($otherItem['rezept'] as $ingredient) {
                            if ($ingredient['id'] === $item['id'] && $ingredient['typ'] === 'item') {
                                // Finde die Fraktionen dieses Items
                                $consumerFraktionen = [];
                                foreach ($fraktionen as $f) {
                                    if (in_array($f['id'], $otherItemFraktionen)) {
                                        $consumerFraktionen[] = $f;
                                    }
                                }
                                
                                foreach ($consumerFraktionen as $consumerFraktion) {
                                    $itemConsumers[] = [
                                        'fraktion_id' => $consumerFraktion['id'],
                                        'fraktion_name' => $consumerFraktion['name'],
                                        'item_id' => $otherItem['id'],
                                        'item_name' => $otherItem['name'],
                                        'menge' => $ingredient['menge']
                                    ];
                                }
                            }
                        }
                    }
                }
                
                if (!empty($itemConsumers)) {
                    $consumers[$item['id']] = $itemConsumers;
                }
            }
            
            $response = [
                'success' => true,
                'items' => $aufbereiteteItems,
                'consumers' => $consumers
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Keine Fraktions-ID angegeben'
            ];
        }
        break;
    
    // Hole alle Materialien einer Fraktion
    case 'get_fraktion_materialien':
        if (isset($_GET['id'])) {
            $fraktionId = $_GET['id'];
            $materialien = getMaterialienForFraktion($fraktionId);
            $alleItems = loadItems();
            
            // Bereite die Daten für die Antwort vor
            $aufbereiteteMaterialien = [];
            foreach ($materialien as $material) {
                $aufbereitetesMaterial = [
                    'id' => $material['id'],
                    'name' => $material['name'],
                    'beschreibung' => $material['beschreibung'] ?? ''
                ];
                
                $aufbereiteteMaterialien[] = $aufbereitetesMaterial;
            }
            
            // Finde Items, die diese Materialien verwenden
            $consumers = [];
            
            foreach ($aufbereiteteMaterialien as $material) {
                $materialConsumers = [];
                
                foreach ($alleItems as $item) {
                    if (isset($item['rezept']) && is_array($item['rezept'])) {
                        foreach ($item['rezept'] as $bestandteil) {
                            if (isset($bestandteil['typ']) && $bestandteil['typ'] === 'material' && 
                                isset($bestandteil['id']) && $bestandteil['id'] === $material['id']) {
                                
                                // Finde die Fraktionen dieses Items
                                $itemFraktionen = [];
                                $fraktionen = loadFraktionen();
                                
                                // Prüfe neue Datenstruktur mit mehreren Fraktionen
                                if (isset($item['fraktionen']) && is_array($item['fraktionen'])) {
                                    foreach ($fraktionen as $fraktion) {
                                        if (in_array($fraktion['id'], $item['fraktionen'])) {
                                            $itemFraktionen[] = [
                                                'id' => $fraktion['id'],
                                                'name' => $fraktion['name']
                                            ];
                                        }
                                    }
                                }
                                // Fallback auf altes Format
                                elseif (isset($item['fraktion_id'])) {
                                    foreach ($fraktionen as $fraktion) {
                                        if ($fraktion['id'] === $item['fraktion_id']) {
                                            $itemFraktionen[] = [
                                                'id' => $fraktion['id'],
                                                'name' => $fraktion['name']
                                            ];
                                        }
                                    }
                                }
                                
                                $materialConsumers[] = [
                                    'item_id' => $item['id'],
                                    'item_name' => $item['name'],
                                    'menge' => $bestandteil['menge'] ?? 1,
                                    'fraktionen' => $itemFraktionen
                                ];
                            }
                        }
                    }
                }
                
                if (!empty($materialConsumers)) {
                    $consumers[$material['id']] = $materialConsumers;
                }
            }
            
            $response = [
                'success' => true,
                'materialien' => $aufbereiteteMaterialien,
                'consumers' => $consumers
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Keine Fraktions-ID angegeben'
            ];
        }
        break;
        
    // Hole Details für ein Material
    case 'get_material_details':
        if (isset($_GET['id'])) {
            $materialId = $_GET['id'];
            $materialien = loadMaterialien();
            $items = loadItems();
            $fraktionen = loadFraktionen();
            
            $material = null;
            foreach ($materialien as $mat) {
                if ($mat['id'] === $materialId) {
                    $material = $mat;
                    break;
                }
            }
            
            if ($material) {
                // Finde Produzenten (Fraktionen)
                $fraktionName = "Nicht zugewiesen";
                $produzenten = [];
                
                // Prüfe, ob das neue Format mit mehreren Fraktionen verwendet wird
                if (isset($material['fraktionen']) && is_array($material['fraktionen']) && !empty($material['fraktionen'])) {
                    foreach ($fraktionen as $fraktion) {
                        if (in_array($fraktion['id'], $material['fraktionen'])) {
                            $produzenten[] = [
                                'id' => $fraktion['id'],
                                'name' => $fraktion['name']
                            ];
                        }
                    }
                } 
                // Fallback auf das alte Format mit einzelner fraktion_id
                else if (!empty($material['fraktion_id'])) {
                    foreach ($fraktionen as $fraktion) {
                        if ($fraktion['id'] === $material['fraktion_id']) {
                            $fraktionName = $fraktion['name'];
                            $produzenten[] = [
                                'id' => $fraktion['id'],
                                'name' => $fraktion['name']
                            ];
                            break;
                        }
                    }
                }
                
                // Für Abwärtskompatibilität: Setze den ersten Produzenten als Hauptproduzent
                $produzent = !empty($produzenten) ? $produzenten[0] : null;
                
                // Finde Verwendungen in Rezepten (Konsumenten)
                $verwendungen = getVerwendungenInRezepten('material', $materialId, $items);
                $verwendungenListe = []; // Alte Version für Abwärtskompatibilität
                $konsumentenInfo = [];
                $konsumentenNachFraktion = [];
                
                foreach ($verwendungen as $item) {
                    // Finde die Fraktion dieses Items
                    $itemFraktion = "Unbekannt";
                    $itemFraktionId = null;
                    foreach ($fraktionen as $f) {
                        if ($f['id'] === $item['fraktion_id']) {
                            $itemFraktion = $f['name'];
                            $itemFraktionId = $f['id'];
                            break;
                        }
                    }
                    
                    // Für Abwärtskompatibilität
                    $verwendungenListe[] = [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'fraktion_id' => $itemFraktionId,
                        'fraktion' => $itemFraktion
                    ];
                    
                    // Finde die benötigte Menge
                    $menge = 0;
                    if (isset($item['rezept']) && is_array($item['rezept'])) {
                        foreach ($item['rezept'] as $bestandteil) {
                            if ($bestandteil['typ'] === 'material' && $bestandteil['id'] === $materialId) {
                                $menge = $bestandteil['menge'];
                                break;
                            }
                        }
                    }
                    
                    $konsument = [
                        'id' => $item['id'],
                        'item_name' => $item['name'],
                        'menge' => $menge,
                        'fraktion_id' => $itemFraktionId,
                        'fraktion_name' => $itemFraktion
                    ];
                    
                    $konsumentenInfo[] = $konsument;
                    
                    // Gruppenweise nach Fraktion
                    if (!isset($konsumentenNachFraktion[$itemFraktionId])) {
                        $konsumentenNachFraktion[$itemFraktionId] = [
                            'name' => $itemFraktion,
                            'items' => []
                        ];
                    }
                    $konsumentenNachFraktion[$itemFraktionId]['items'][] = $konsument;
                }
                
                $response = [
                    'success' => true,
                    'material' => [
                        'id' => $material['id'],
                        'name' => $material['name'],
                        'beschreibung' => $material['beschreibung'],
                        'fraktion' => $fraktionName,
                        'verwendet_in' => $verwendungenListe
                    ],
                    'produzent' => $produzent,                // Für alte Clients - erster Produzent
                    'produzenten' => $produzenten,            // Neue Liste aller produzierenden Fraktionen
                    'konsumenten' => $konsumentenInfo,
                    'konsumentenNachFraktion' => $konsumentenNachFraktion
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Material nicht gefunden'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Keine Material-ID angegeben'
            ];
        }
        break;

    // Hole Details für ein Item
    case 'get_item_details':
        if (isset($_GET['id'])) {
            $itemId = $_GET['id'];
            $items = loadItems();
            $materialien = loadMaterialien();
            $fraktionen = loadFraktionen();
            
            $item = null;
            foreach ($items as $itm) {
                if ($itm['id'] === $itemId) {
                    $item = $itm;
                    break;
                }
            }
            
            if ($item) {
                // Finde Produzenten (Fraktionen)
                $fraktionName = "Nicht zugewiesen";
                $produzenten = [];
                
                // Prüfe, ob das neue Format mit mehreren Fraktionen verwendet wird
                if (isset($item['fraktionen']) && is_array($item['fraktionen']) && !empty($item['fraktionen'])) {
                    foreach ($fraktionen as $fraktion) {
                        if (in_array($fraktion['id'], $item['fraktionen'])) {
                            $produzenten[] = [
                                'id' => $fraktion['id'],
                                'name' => $fraktion['name']
                            ];
                        }
                    }
                } 
                // Fallback auf das alte Format mit einzelner fraktion_id
                else if (!empty($item['fraktion_id'])) {
                    foreach ($fraktionen as $fraktion) {
                        if ($fraktion['id'] === $item['fraktion_id']) {
                            $fraktionName = $fraktion['name'];
                            $produzenten[] = [
                                'id' => $fraktion['id'],
                                'name' => $fraktion['name']
                            ];
                            break;
                        }
                    }
                }
                
                // Für Abwärtskompatibilität: Setze den ersten Produzenten als Hauptproduzent
                $produzent = !empty($produzenten) ? $produzenten[0] : null;
                
                // Bereite Rezept auf
                $rezeptBestandteile = [];
                $lieferantenInfo = []; // Informationen über die Lieferanten der Bestandteile
                
                if (isset($item['rezept']) && is_array($item['rezept'])) {
                    foreach ($item['rezept'] as $bestandteil) {
                        $typ = isset($bestandteil['typ']) ? $bestandteil['typ'] : '';
                        $id = isset($bestandteil['id']) ? $bestandteil['id'] : '';
                        $menge = isset($bestandteil['menge']) ? $bestandteil['menge'] : 1;
                        $name = 'Unbekannt';
                        $fraktionId = null;
                        $fraktionName = 'Unbekannt';
                        
                        if ($typ === 'material') {
                            foreach ($materialien as $material) {
                                if ($material['id'] === $id) {
                                    $name = $material['name'];
                                    $fraktionId = $material['fraktion_id'];
                                    
                                    // Finde den Namen der Fraktion für dieses Material
                                    foreach ($fraktionen as $fraktion) {
                                        if ($fraktion['id'] === $fraktionId) {
                                            $fraktionName = $fraktion['name'];
                                            break;
                                        }
                                    }
                                    break;
                                }
                            }
                        } elseif ($typ === 'item') {
                            foreach ($items as $innerItem) {
                                if ($innerItem['id'] === $id) {
                                    $name = $innerItem['name'];
                                    $fraktionId = $innerItem['fraktion_id'];
                                    
                                    // Finde den Namen der Fraktion für dieses Item
                                    foreach ($fraktionen as $fraktion) {
                                        if ($fraktion['id'] === $fraktionId) {
                                            $fraktionName = $fraktion['name'];
                                            break;
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                        
                        $rezeptBestandteile[] = [
                            'id' => $id,
                            'typ' => $typ,
                            'name' => $name,
                            'menge' => $menge
                        ];
                        
                        // Füge Lieferanteninformation hinzu, wenn die Fraktion nicht dieselbe ist wie die des Items
                        if ($fraktionId && $fraktionId !== $item['fraktion_id']) {
                            $lieferantenInfo[] = [
                                'id' => $id,
                                'typ' => $typ,
                                'name' => $name,
                                'menge' => $menge,
                                'fraktion_id' => $fraktionId,
                                'fraktion_name' => $fraktionName
                            ];
                        }
                    }
                }
                
                // Finde Verwendungen in anderen Rezepten (Konsumenten)
                $verwendungen = getVerwendungenInRezepten('item', $itemId, $items);
                $konsumentenInfo = [];
                $verwendungenListe = []; // Die alte verwendungenListe für Abwärtskompatibilität
                
                foreach ($verwendungen as $verwendung) {
                    // Finde die Fraktion dieses konsumierenden Items
                    $verwendungFraktionId = $verwendung['fraktion_id'];
                    $verwendungFraktionName = 'Unbekannt';
                    
                    foreach ($fraktionen as $fraktion) {
                        if ($fraktion['id'] === $verwendungFraktionId) {
                            $verwendungFraktionName = $fraktion['name'];
                            break;
                        }
                    }
                    
                    // Finde die benötigte Menge
                    $menge = 0;
                    if (isset($verwendung['rezept']) && is_array($verwendung['rezept'])) {
                        foreach ($verwendung['rezept'] as $bestandteil) {
                            if ($bestandteil['typ'] === 'item' && $bestandteil['id'] === $itemId) {
                                $menge = $bestandteil['menge'];
                                break;
                            }
                        }
                    }
                    
                    $konsumentenInfo[] = [
                        'id' => $verwendung['id'],
                        'item_name' => $verwendung['name'],
                        'menge' => $menge,
                        'fraktion_id' => $verwendungFraktionId,
                        'fraktion_name' => $verwendungFraktionName
                    ];
                    
                    // Alte verwendungenListe für Abwärtskompatibilität
                    $verwendungenListe[] = [
                        'id' => $verwendung['id'],
                        'name' => $verwendung['name']
                    ];
                }
                
                $response = [
                    'success' => true,
                    'item' => [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'beschreibung' => $item['beschreibung'],
                        'fraktion' => $fraktionName,
                        'rezept' => $rezeptBestandteile,
                        'verwendet_in' => $verwendungenListe
                    ],
                    'produzent' => $produzent,                // Für alte Clients - erster Produzent
                    'produzenten' => $produzenten,            // Neue Liste aller produzierenden Fraktionen
                    'konsumenten' => $konsumentenInfo,
                    'bezieht_von' => $lieferantenInfo
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Item nicht gefunden'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Keine Item-ID angegeben'
            ];
        }
        break;
        
    // Hole Details für eine Produktionsroute
    case 'get_route_details':
        if (isset($_GET['id'])) {
            $itemId = $_GET['id'];
            $items = loadItems();
            $materialien = loadMaterialien();
            $fraktionen = loadFraktionen();
            
            $item = null;
            foreach ($items as $itm) {
                if ($itm['id'] === $itemId) {
                    $item = $itm;
                    break;
                }
            }
            
            if ($item) {
                $route = erstelleProduktionsroute($item, $items, $materialien, $fraktionen);
                
                if ($route) {
                    $response = [
                        'success' => true,
                        'route' => $route
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Keine Route für dieses Item verfügbar'
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Item nicht gefunden'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Keine Item-ID angegeben'
            ];
        }
        break;
        
    default:
        $response = [
            'success' => false,
            'message' => 'Unbekannte Aktion: ' . $action
        ];
        break;
}

// Gebe JSON-Antwort zurück
echo json_encode($response);
exit;