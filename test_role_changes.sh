#!/bin/bash
# Quick test script for role changes

echo "=== ROLLEN-ÄNDERUNGS-TEST ==="
echo ""
echo "1. Erstelle Backup..."
php backup_data.php | tail -1

echo ""
echo "2. Zeige aktuelle Rollen:"
php -r "
require_once 'includes/db.php';
\$roles = getJsonData('roles.json');
foreach (\$roles as \$role) {
    \$count = isset(\$role['permissions']) ? count(\$role['permissions']) : 0;
    echo \"  - {\$role['name']}: \$count Module\n\";
}
"

echo ""
echo "✅ Du kannst jetzt data/roles.json bearbeiten"
echo "⚠️  Bei Problemen: php restore_data.php"
