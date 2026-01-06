#!/bin/bash
# GitHub Webhook Handler für automatisches Deployment

echo "Content-type: text/html"
echo ""
echo "<h1>Git Pull ausgeführt</h1>"

# In Projektverzeichnis wechseln
cd /var/www/vhosts/5-9-96-43.plesk.page/httpdocs

# Git Pull
git pull origin main 2>&1

echo "<hr>"
echo "<p>Fertig! Bitte Seite neu laden.</p>"
echo "<p><a href='/modules/licenses.php'>Zur Lizenzverwaltung</a></p>
