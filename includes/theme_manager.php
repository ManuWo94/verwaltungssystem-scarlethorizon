<?php
/**
 * Aktenverwaltungssystem - Department of Justice
 * Theme Manager
 * 
 * Verwaltet die benutzerdefinierten Farbpaletten und Theme-Einstellungen
 */

// Stelle sicher, dass die Datenbankfunktionen verfügbar sind
if (!function_exists('loadJsonData')) {
    require_once __DIR__ . '/db.php';
}

/**
 * Holt die aktuelle Farbpalette aus der JSON-Datei
 * 
 * @return array Die aktuelle Farbpalette
 */
function getCurrentTheme() {
    $defaultTheme = [
        'name' => 'Standard',
        'colors' => [
            'primary' => '#0e1214',
            'secondary' => '#5a4a37',
            'accent' => '#6a2e09',
            'light' => '#e8e2d8',
            'dark' => '#0e0e0e',
            'paper' => '#a19a88',
            'ink' => '#121212',
            'success' => '#1c3821',
            'danger' => '#660000',
            'warning' => '#6a5800',
            'info' => '#192a41',
            'sidebar-bg' => '#1a1f23',
            'sidebar-color' => '#e8e2d8',
            'content-bg' => '#d0c9bc',
        ]
    ];
    
    $themes = loadJsonData('themes.json');
    
    if (empty($themes)) {
        return $defaultTheme;
    }
    
    // Finde das aktive Theme
    foreach ($themes as $theme) {
        if (isset($theme['active']) && $theme['active']) {
            return $theme;
        }
    }
    
    // Wenn kein aktives Theme gefunden wurde, return das erste
    return $themes[0] ?? $defaultTheme;
}

/**
 * Speichert ein neues Theme in der JSON-Datei
 * 
 * @param array $theme Das zu speichernde Theme
 * @return bool Erfolg oder Misserfolg
 */
function saveTheme($theme) {
    $themes = loadJsonData('themes.json') ?: [];
    
    // Existiert das Theme bereits?
    $themeExists = false;
    foreach ($themes as $key => $existingTheme) {
        // Wenn dies das neue aktive Theme ist, deaktiviere alle anderen
        if (isset($theme['active']) && $theme['active']) {
            $themes[$key]['active'] = false;
        }
        
        // Wenn ein Theme mit dem gleichen Namen existiert, aktualisiere es
        if ($existingTheme['name'] === $theme['name']) {
            $themes[$key] = $theme;
            $themeExists = true;
        }
    }
    
    // Wenn das Theme nicht existiert, füge es hinzu
    if (!$themeExists) {
        $themes[] = $theme;
    }
    
    return saveJsonData('themes.json', $themes);
}

/**
 * Generiert ein CSS-Snippet für die aktuelle Farbpalette
 * 
 * @return string CSS-Variablen für die aktuelle Farbpalette
 */
function generateThemeCSS() {
    $theme = getCurrentTheme();
    $css = ":root {\n";
    
    foreach ($theme['colors'] as $name => $color) {
        $css .= "    --" . $name . ": " . $color . ";\n";
    }
    
    $css .= "}\n";
    return $css;
}

/**
 * Gibt vordefinierte historische Farbpaletten zurück
 * 
 * @return array Liste der vordefinierten Farbpaletten
 */
function getPredefinedThemes() {
    return [
        [
            'name' => 'Federal Archive (1899)',
            'colors' => [
                'primary' => '#0e1214',
                'secondary' => '#5a4a37',
                'accent' => '#6a2e09',
                'light' => '#e8e2d8',
                'dark' => '#0e0e0e',
                'paper' => '#a19a88',
                'ink' => '#121212',
                'success' => '#1c3821',
                'danger' => '#660000',
                'warning' => '#6a5800',
                'info' => '#192a41',
                'sidebar-bg' => '#1a1f23',
                'sidebar-color' => '#e8e2d8',
                'content-bg' => '#d0c9bc',
            ]
        ],
        [
            'name' => 'Sepia Archive',
            'colors' => [
                'primary' => '#2c1704',
                'secondary' => '#6b4f34',
                'accent' => '#8b4513',
                'light' => '#f2e8d5',
                'dark' => '#1a0d02',
                'paper' => '#d9c7a7',
                'ink' => '#0d0901',
                'success' => '#2d4925',
                'danger' => '#7b0000',
                'warning' => '#7a6307',
                'info' => '#1a384f',
                'sidebar-bg' => '#2c1704',
                'sidebar-color' => '#f2e8d5',
                'content-bg' => '#e8dbbd',
            ]
        ],
        [
            'name' => 'Prussian Blue',
            'colors' => [
                'primary' => '#173042',
                'secondary' => '#406882',
                'accent' => '#9d3f19',
                'light' => '#e0ebf3',
                'dark' => '#0d1b24',
                'paper' => '#b9cad8',
                'ink' => '#0d1b24',
                'success' => '#244b2c',
                'danger' => '#8b0000',
                'warning' => '#8b6914',
                'info' => '#234761',
                'sidebar-bg' => '#173042',
                'sidebar-color' => '#e0ebf3',
                'content-bg' => '#d6e1eb',
            ]
        ],
        [
            'name' => 'Justizpapier',
            'colors' => [
                'primary' => '#231f20',
                'secondary' => '#70584b',
                'accent' => '#7f2d15',
                'light' => '#f5f2eb',
                'dark' => '#1a1918',
                'paper' => '#cfc5b5',
                'ink' => '#231f20',
                'success' => '#284e2c',
                'danger' => '#7f0000',
                'warning' => '#7f5e14',
                'info' => '#1c3b59',
                'sidebar-bg' => '#231f20',
                'sidebar-color' => '#f5f2eb',
                'content-bg' => '#e6e0d5',
            ]
        ],
        [
            'name' => 'Amtlicher Stempel',
            'colors' => [
                'primary' => '#031f3c',
                'secondary' => '#4d3a26',
                'accent' => '#9e1b10',
                'light' => '#f0f0eb',
                'dark' => '#031428',
                'paper' => '#c9c5b8',
                'ink' => '#031428',
                'success' => '#1c432e',
                'danger' => '#8c0f0f',
                'warning' => '#7a581e',
                'info' => '#1c3f6a',
                'sidebar-bg' => '#031f3c',
                'sidebar-color' => '#f0f0eb',
                'content-bg' => '#dfdfd5',
            ]
        ],
        // Dunkle Themes
        [
            'name' => 'Dunkle Tinte',
            'colors' => [
                'primary' => '#121212',
                'secondary' => '#2e2e2e',
                'accent' => '#7d4e2a',
                'light' => '#e0e0e0',
                'dark' => '#0a0a0a',
                'paper' => '#1e1e1e',
                'ink' => '#d8d8d8',
                'success' => '#2e7d32',
                'danger' => '#c62828',
                'warning' => '#f9a825',
                'info' => '#1565c0',
                'sidebar-bg' => '#121212',
                'sidebar-color' => '#e0e0e0',
                'content-bg' => '#2d2d2d',
            ]
        ],
        [
            'name' => 'Mitternachtsbüro',
            'colors' => [
                'primary' => '#1a1a2e',
                'secondary' => '#16213e',
                'accent' => '#a34a28',
                'light' => '#e7e6e1',
                'dark' => '#0f0f1a',
                'paper' => '#222236',
                'ink' => '#e7e6e1',
                'success' => '#386641',
                'danger' => '#9e2a2b',
                'warning' => '#d09b2c',
                'info' => '#184e77',
                'sidebar-bg' => '#0f0f1a',
                'sidebar-color' => '#e7e6e1',
                'content-bg' => '#2b2b44',
            ]
        ],
        [
            'name' => 'Sephia Nacht',
            'colors' => [
                'primary' => '#2d1b00',
                'secondary' => '#422800',
                'accent' => '#904e2b',
                'light' => '#f5e6cc',
                'dark' => '#170e00',
                'paper' => '#382200',
                'ink' => '#e6d7b8',
                'success' => '#2c5530',
                'danger' => '#a7201f',
                'warning' => '#cf9c27',
                'info' => '#29679e',
                'sidebar-bg' => '#221500',
                'sidebar-color' => '#f5e6cc',
                'content-bg' => '#4d2e00',
            ]
        ],
        // Augenfreundliche Themes
        [
            'name' => 'Augenschonend Amber',
            'colors' => [
                'primary' => '#433931',
                'secondary' => '#6b5d4f',
                'accent' => '#8b5a2b',
                'light' => '#ffeecc',
                'dark' => '#242018',
                'paper' => '#ffe6b3',
                'ink' => '#2d2720',
                'success' => '#4d6a59',
                'danger' => '#a65046',
                'warning' => '#d4a150',
                'info' => '#507b9c',
                'sidebar-bg' => '#3a322b',
                'sidebar-color' => '#ffeecc',
                'content-bg' => '#fff2d6',
            ]
        ],
        [
            'name' => 'Augenschonend Blau',
            'colors' => [
                'primary' => '#2c3e50',
                'secondary' => '#5d6d7e',
                'accent' => '#d35400',
                'light' => '#ecf0f1',
                'dark' => '#1c2833',
                'paper' => '#d6eaf8',
                'ink' => '#2c3e50',
                'success' => '#27ae60',
                'danger' => '#c0392b',
                'warning' => '#f39c12',
                'info' => '#2980b9',
                'sidebar-bg' => '#1c2833',
                'sidebar-color' => '#ecf0f1',
                'content-bg' => '#d6eaf8',
            ]
        ],
        [
            'name' => 'Historisches Grün',
            'colors' => [
                'primary' => '#1e352f',
                'secondary' => '#335c49',
                'accent' => '#a8763e',
                'light' => '#eef1e6',
                'dark' => '#16261e',
                'paper' => '#dae2d5',
                'ink' => '#1e352f',
                'success' => '#3a7563',
                'danger' => '#9e3a3a',
                'warning' => '#d9a566',
                'info' => '#3a6ea5',
                'sidebar-bg' => '#1e352f',
                'sidebar-color' => '#eef1e6',
                'content-bg' => '#dad7cd',
            ]
        ],
        // Edle Themes
        [
            'name' => 'Königliches Mahagoni',
            'colors' => [
                'primary' => '#311102',
                'secondary' => '#6e392c',
                'accent' => '#9e7240',
                'light' => '#f9f1e7',
                'dark' => '#1e0801',
                'paper' => '#eddecb',
                'ink' => '#311102',
                'success' => '#2a5c3e',
                'danger' => '#952b22',
                'warning' => '#b3832f',
                'info' => '#245073',
                'sidebar-bg' => '#2a0f02',
                'sidebar-color' => '#f9f1e7',
                'content-bg' => '#f1e7d8',
            ]
        ],
        [
            'name' => 'Grand Hotel',
            'colors' => [
                'primary' => '#14252c',
                'secondary' => '#4e5d63',
                'accent' => '#c9a87b',
                'light' => '#f7f3ed',
                'dark' => '#0b151a',
                'paper' => '#e8e3db',
                'ink' => '#14252c',
                'success' => '#3a6a5c',
                'danger' => '#7d2f2f',
                'warning' => '#c49c52',
                'info' => '#366585',
                'sidebar-bg' => '#14252c',
                'sidebar-color' => '#f7f3ed',
                'content-bg' => '#e8e3db',
            ]
        ],
        [
            'name' => 'Imperial Gold',
            'colors' => [
                'primary' => '#1c1408',
                'secondary' => '#564830',
                'accent' => '#d4b254',
                'light' => '#fcf8e8',
                'dark' => '#0f0a04',
                'paper' => '#f4ebd1',
                'ink' => '#1c1408',
                'success' => '#31593e',
                'danger' => '#912f2a',
                'warning' => '#c4963d',
                'info' => '#2c5984',
                'sidebar-bg' => '#1c1408',
                'sidebar-color' => '#fcf8e8',
                'content-bg' => '#f4ebd1',
            ]
        ],
        // Wild West Themes
        [
            'name' => 'Saloon',
            'colors' => [
                'primary' => '#3e2715',
                'secondary' => '#7d5425',
                'accent' => '#bb5b34',
                'light' => '#f8f0e3',
                'dark' => '#271809',
                'paper' => '#e5d7b9',
                'ink' => '#3e2715',
                'success' => '#3e693c',
                'danger' => '#94302a',
                'warning' => '#d19f37',
                'info' => '#385c8a',
                'sidebar-bg' => '#3e2715',
                'sidebar-color' => '#f8f0e3',
                'content-bg' => '#e5d7b9',
            ]
        ],
        [
            'name' => 'Sheriff\'s Office',
            'colors' => [
                'primary' => '#26201a',
                'secondary' => '#614c32',
                'accent' => '#a84e2d',
                'light' => '#f5efe6',
                'dark' => '#1a1510',
                'paper' => '#e8dcca',
                'ink' => '#26201a',
                'success' => '#2c5340',
                'danger' => '#8b2d28',
                'warning' => '#bc8a37',
                'info' => '#284863',
                'sidebar-bg' => '#26201a',
                'sidebar-color' => '#f5efe6',
                'content-bg' => '#e8dcca',
            ]
        ],
        [
            'name' => 'Dusty Trail',
            'colors' => [
                'primary' => '#3c3124',
                'secondary' => '#6d5b46',
                'accent' => '#bf6a38',
                'light' => '#f0e8d9',
                'dark' => '#26201a',
                'paper' => '#d6c8ad',
                'ink' => '#3c3124',
                'success' => '#3d5d3b',
                'danger' => '#85423d',
                'warning' => '#c7a550',
                'info' => '#3c5775',
                'sidebar-bg' => '#3c3124',
                'sidebar-color' => '#f0e8d9',
                'content-bg' => '#d6c8ad',
            ]
        ],
        [
            'name' => 'Homestead',
            'colors' => [
                'primary' => '#352d1f',
                'secondary' => '#6c5b40',
                'accent' => '#bc8344',
                'light' => '#f7f2e4',
                'dark' => '#1c1811',
                'paper' => '#e6ddc8',
                'ink' => '#352d1f',
                'success' => '#3d6141',
                'danger' => '#92443d',
                'warning' => '#bc8f3c',
                'info' => '#366389',
                'sidebar-bg' => '#352d1f',
                'sidebar-color' => '#f7f2e4',
                'content-bg' => '#e6ddc8',
            ]
        ]
    ];
}