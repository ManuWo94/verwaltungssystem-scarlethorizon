<?php
/**
 * Datenverarbeitungsfunktionen für JSON-Speicherung
 */

/**
 * Lädt alle Fraktionen aus der JSON-Datei
 * 
 * @return array
 */
function loadFraktionen() {
    if (file_exists(FRAKTIONEN_FILE)) {
        $fraktionen = json_decode(file_get_contents(FRAKTIONEN_FILE), true);
        
        // Wenn die Datei leer ist oder ungültiges JSON enthält
        if ($fraktionen === null) {
            return [];
        }
        
        return $fraktionen;
    }
    
    return [];
}

/**
 * Speichert alle Fraktionen in die JSON-Datei
 * 
 * @param array $fraktionen
 * @return bool
 */
function saveFraktionen($fraktionen) {
    return file_put_contents(FRAKTIONEN_FILE, json_encode($fraktionen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Erstellt eine neue Fraktion
 * 
 * @param array $fraktion
 * @return bool
 */
function createFraktion($fraktion) {
    $fraktionen = loadFraktionen();
    
    // ID generieren, falls keine vorhanden
    if (!isset($fraktion['id']) || empty($fraktion['id'])) {
        $fraktion['id'] = generateUniqueId();
    }
    
    // Prüfen, ob eine Fraktion mit gleichem Namen bereits existiert
    $fractionNameExists = false;
    foreach ($fraktionen as $existingFraktion) {
        if (strtolower(trim($existingFraktion['name'])) === strtolower(trim($fraktion['name']))) {
            $fractionNameExists = true;
            break;
        }
    }
    
    if ($fractionNameExists) {
        return false; // Fraktion mit gleichem Namen existiert bereits
    }
    
    $fraktionen[] = $fraktion;
    
    return saveFraktionen($fraktionen);
}

/**
 * Aktualisiert eine bestehende Fraktion
 * 
 * @param array $updatedFraktion
 * @return bool
 */
function updateFraktion($updatedFraktion) {
    $fraktionen = loadFraktionen();
    $updated = false;
    
    foreach ($fraktionen as $key => $fraktion) {
        if ($fraktion['id'] === $updatedFraktion['id']) {
            $fraktionen[$key] = $updatedFraktion;
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        return saveFraktionen($fraktionen);
    }
    
    return false;
}

/**
 * Löscht eine Fraktion
 * 
 * @param string $id
 * @return bool
 */
function deleteFraktion($id) {
    $fraktionen = loadFraktionen();
    $deleted = false;
    
    foreach ($fraktionen as $key => $fraktion) {
        if ($fraktion['id'] === $id) {
            unset($fraktionen[$key]);
            $deleted = true;
            break;
        }
    }
    
    if ($deleted) {
        // Neu-Indizierung des Arrays nach Löschung
        $fraktionen = array_values($fraktionen);
        
        // Entferne die Fraktion aus allen Items und Materialien
        $items = loadItems();
        foreach ($items as $key => $item) {
            if (isset($item['fraktion_id']) && $item['fraktion_id'] === $id) {
                $items[$key]['fraktion_id'] = '';
            }
        }
        saveItems($items);
        
        $materialien = loadMaterialien();
        foreach ($materialien as $key => $material) {
            if (isset($material['fraktion_id']) && $material['fraktion_id'] === $id) {
                $materialien[$key]['fraktion_id'] = '';
            }
        }
        saveMaterialien($materialien);
        
        return saveFraktionen($fraktionen);
    }
    
    return false;
}

/**
 * Lädt alle Items aus der JSON-Datei
 * 
 * @return array
 */
function loadItems() {
    if (file_exists(ITEMS_FILE)) {
        $items = json_decode(file_get_contents(ITEMS_FILE), true);
        
        // Wenn die Datei leer ist oder ungültiges JSON enthält
        if ($items === null) {
            return [];
        }
        
        return $items;
    }
    
    return [];
}

/**
 * Speichert alle Items in die JSON-Datei
 * 
 * @param array $items
 * @return bool
 */
function saveItems($items) {
    return file_put_contents(ITEMS_FILE, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Erstellt ein neues Item
 * 
 * @param array $item
 * @return bool
 */
function createItem($item) {
    $items = loadItems();
    
    // ID generieren, falls keine vorhanden
    if (!isset($item['id']) || empty($item['id'])) {
        $item['id'] = generateUniqueId();
    }
    
    // Prüfen, ob ein Item mit gleichem Namen bereits existiert
    $itemNameExists = false;
    foreach ($items as $existingItem) {
        if (strtolower(trim($existingItem['name'])) === strtolower(trim($item['name']))) {
            $itemNameExists = true;
            break;
        }
    }
    
    if ($itemNameExists) {
        return false; // Item mit gleichem Namen existiert bereits
    }
    
    // Rezeptbestandteile verarbeiten
    if (isset($item['rezept']) && is_array($item['rezept'])) {
        foreach ($item['rezept'] as $key => $bestandteil) {
            // Prüfen, ob es sich um ein Material oder Item handelt
            if (isset($bestandteil['id'])) {
                if (strpos($bestandteil['id'], 'material_') === 0) {
                    $item['rezept'][$key]['typ'] = 'material';
                    $item['rezept'][$key]['id'] = substr($bestandteil['id'], 9);
                } elseif (strpos($bestandteil['id'], 'item_') === 0) {
                    $item['rezept'][$key]['typ'] = 'item';
                    $item['rezept'][$key]['id'] = substr($bestandteil['id'], 5);
                }
            }
        }
    }
    
    $items[] = $item;
    
    return saveItems($items);
}

/**
 * Aktualisiert ein bestehendes Item
 * 
 * @param array $updatedItem
 * @return bool
 */
function updateItem($updatedItem) {
    $items = loadItems();
    $updated = false;
    
    // Rezeptbestandteile verarbeiten
    if (isset($updatedItem['rezept']) && is_array($updatedItem['rezept'])) {
        foreach ($updatedItem['rezept'] as $key => $bestandteil) {
            // Prüfen, ob es sich um ein Material oder Item handelt
            if (isset($bestandteil['id'])) {
                if (strpos($bestandteil['id'], 'material_') === 0) {
                    $updatedItem['rezept'][$key]['typ'] = 'material';
                    $updatedItem['rezept'][$key]['id'] = substr($bestandteil['id'], 9);
                } elseif (strpos($bestandteil['id'], 'item_') === 0) {
                    $updatedItem['rezept'][$key]['typ'] = 'item';
                    $updatedItem['rezept'][$key]['id'] = substr($bestandteil['id'], 5);
                }
            }
        }
    }
    
    foreach ($items as $key => $item) {
        if ($item['id'] === $updatedItem['id']) {
            $items[$key] = $updatedItem;
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        return saveItems($items);
    }
    
    return false;
}

/**
 * Löscht ein Item
 * 
 * @param string $id
 * @return bool
 */
function deleteItem($id) {
    $items = loadItems();
    $deleted = false;
    
    foreach ($items as $key => $item) {
        if ($item['id'] === $id) {
            unset($items[$key]);
            $deleted = true;
            break;
        }
    }
    
    if ($deleted) {
        // Neu-Indizierung des Arrays nach Löschung
        $items = array_values($items);
        
        // Entferne das Item aus allen Rezepten anderer Items
        foreach ($items as $key => $item) {
            if (isset($item['rezept']) && is_array($item['rezept'])) {
                foreach ($item['rezept'] as $rezeptKey => $bestandteil) {
                    if (isset($bestandteil['typ']) && $bestandteil['typ'] === 'item' && isset($bestandteil['id']) && $bestandteil['id'] === $id) {
                        unset($items[$key]['rezept'][$rezeptKey]);
                    }
                }
                
                // Neu-Indizierung des Rezept-Arrays nach Löschung
                $items[$key]['rezept'] = array_values($items[$key]['rezept']);
            }
        }
        
        return saveItems($items);
    }
    
    return false;
}

/**
 * Lädt alle Materialien aus der JSON-Datei
 * 
 * @return array
 */
function loadMaterialien() {
    if (file_exists(MATERIALIEN_FILE)) {
        $materialien = json_decode(file_get_contents(MATERIALIEN_FILE), true);
        
        // Wenn die Datei leer ist oder ungültiges JSON enthält
        if ($materialien === null) {
            return [];
        }
        
        return $materialien;
    }
    
    return [];
}

/**
 * Speichert alle Materialien in die JSON-Datei
 * 
 * @param array $materialien
 * @return bool
 */
function saveMaterialien($materialien) {
    return file_put_contents(MATERIALIEN_FILE, json_encode($materialien, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Erstellt ein neues Material
 * 
 * @param array $material
 * @return bool
 */
function createMaterial($material) {
    $materialien = loadMaterialien();
    
    // ID generieren, falls keine vorhanden
    if (!isset($material['id']) || empty($material['id'])) {
        $material['id'] = generateUniqueId();
    }
    
    // Prüfen, ob ein Material mit gleichem Namen bereits existiert
    $materialNameExists = false;
    foreach ($materialien as $existingMaterial) {
        if (strtolower(trim($existingMaterial['name'])) === strtolower(trim($material['name']))) {
            $materialNameExists = true;
            break;
        }
    }
    
    if ($materialNameExists) {
        return false; // Material mit gleichem Namen existiert bereits
    }
    
    $materialien[] = $material;
    
    return saveMaterialien($materialien);
}

/**
 * Aktualisiert ein bestehendes Material
 * 
 * @param array $updatedMaterial
 * @return bool
 */
function updateMaterial($updatedMaterial) {
    $materialien = loadMaterialien();
    $updated = false;
    
    foreach ($materialien as $key => $material) {
        if ($material['id'] === $updatedMaterial['id']) {
            $materialien[$key] = $updatedMaterial;
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        return saveMaterialien($materialien);
    }
    
    return false;
}

/**
 * Löscht ein Material
 * 
 * @param string $id
 * @return bool
 */
function deleteMaterial($id) {
    $materialien = loadMaterialien();
    $deleted = false;
    
    foreach ($materialien as $key => $material) {
        if ($material['id'] === $id) {
            unset($materialien[$key]);
            $deleted = true;
            break;
        }
    }
    
    if ($deleted) {
        // Neu-Indizierung des Arrays nach Löschung
        $materialien = array_values($materialien);
        
        // Entferne das Material aus allen Rezepten
        $items = loadItems();
        foreach ($items as $key => $item) {
            if (isset($item['rezept']) && is_array($item['rezept'])) {
                foreach ($item['rezept'] as $rezeptKey => $bestandteil) {
                    if (isset($bestandteil['typ']) && $bestandteil['typ'] === 'material' && isset($bestandteil['id']) && $bestandteil['id'] === $id) {
                        unset($items[$key]['rezept'][$rezeptKey]);
                    }
                }
                
                // Neu-Indizierung des Rezept-Arrays nach Löschung
                $items[$key]['rezept'] = array_values($items[$key]['rezept']);
            }
        }
        saveItems($items);
        
        return saveMaterialien($materialien);
    }
    
    return false;
}
