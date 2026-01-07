<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Zurücksetzen</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .step {
            background: #e8f4f8;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #c82333;
        }
        .btn-primary {
            background: #007bff;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .success {
            color: #28a745;
        }
        .warning {
            color: #ffc107;
            background: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔄 System Zurücksetzen</h1>
        
        <p>Wenn du Probleme mit der Anzeige hast (fehlende Menüpunkte, Sidebar schließt sich), führe diese Schritte durch:</p>
        
        <div class="step">
            <strong>Schritt 1: Session zurücksetzen</strong>
            <p>Klicke auf "Session löschen" um dich abzumelden und alle Session-Daten zu löschen.</p>
            <a href="force_logout.php" class="btn">Session löschen & Ausloggen</a>
        </div>
        
        <div class="step">
            <strong>Schritt 2: Browser-Cache leeren</strong>
            <p>Nach dem Logout drücke:</p>
            <ul>
                <li><strong>Windows/Linux:</strong> Strg + Shift + Delete oder Strg + F5</li>
                <li><strong>Mac:</strong> Cmd + Shift + Delete oder Cmd + Shift + R</li>
            </ul>
            <p>Dann leere den Browser-Cache komplett.</p>
        </div>
        
        <div class="step">
            <strong>Schritt 3: Erneut anmelden</strong>
            <p>Melde dich wieder an. Die aktualisierten Berechtigungen werden nun geladen.</p>
        </div>
        
        <div class="warning">
            ⚠ <strong>Wichtig:</strong> Alle drei Schritte sind notwendig, damit die Änderungen wirksam werden!
        </div>
        
        <hr style="margin: 30px 0;">
        
        <h2>Alternative: Schnell-Reset</h2>
        <p>Dieser Button führt alle Schritte automatisch durch:</p>
        
        <a href="force_logout.php" class="btn btn-primary" onclick="
            // Clear all storage
            try {
                localStorage.clear();
                sessionStorage.clear();
                console.log('Storage gelöscht');
            } catch(e) {
                console.error('Storage konnte nicht gelöscht werden', e);
            }
            // Clear service workers
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                    for(let registration of registrations) {
                        registration.unregister();
                    }
                });
            }
            return true;
        ">🔄 Alles zurücksetzen & neu starten</a>
        
        <br><br>
        
        <a href="dashboard.php">← Zurück zum Dashboard</a>
    </div>
    
    <script>
        console.log('[Reset] Seite geladen');
        console.log('[Reset] LocalStorage Items:', localStorage.length);
        console.log('[Reset] SessionStorage Items:', sessionStorage.length);
    </script>
</body>
</html>
