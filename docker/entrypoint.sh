#!/bin/sh
# ---------------------------------------------------------------------------
# Démarrage du conteneur de démonstration.
#
# Trois choses dans l'ordre : la configuration, la base, le serveur web. Si
# l'une échoue, on s'arrête là plutôt que de servir une boutique à moitié
# installée.
# ---------------------------------------------------------------------------
set -eu

racine=/var/www/html
cd "$racine"

# Render fournit le port à écouter ; 10000 est sa valeur par défaut.
port="${PORT:-10000}"

echo "→ Configuration depuis l'environnement"
php docker/config-depuis-env.php

# Le fichier est écrit par ce script, qui tourne en root ; c'est Apache, en
# www-data, qui devra le lire. Sans ce changement de propriétaire, le site
# répond « Permission denied » dès la première page.
chown www-data:www-data config/config.php
chmod 600 config/config.php

echo "→ Démarrage de MariaDB"
mkdir -p /run/mysqld
chown -R mysql:mysql /run/mysqld /var/lib/mysql

# Première ouverture du conteneur : le dossier de données est vide.
if [ ! -d /var/lib/mysql/mysql ]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
fi

mariadbd-safe --skip-syslog --user=mysql >/dev/null 2>&1 &

# On attend qu'elle réponde plutôt que de dormir un temps arbitraire : trop
# court, l'installation échoue ; trop long, le déploiement traîne.
attente=0
until mariadb-admin ping --silent 2>/dev/null; do
    attente=$((attente + 1))
    if [ "$attente" -gt 60 ]; then
        echo "MariaDB n'a pas démarré en soixante secondes." >&2
        exit 1
    fi
    sleep 1
done

# MariaDB authentifie son compte root par le socket Unix : cela fonctionne ici,
# où le script tourne en root, mais pas depuis Apache qui tourne en www-data.
# La boutique reçoit donc son propre utilisateur, avec des droits sur sa seule
# base — ce qu'on lui donnerait chez n'importe quel hébergeur.
base="${BOUGE_DB_NAME:-bouge}"
utilisateur="${BOUGE_DB_USER:-bouge}"
motdepasse="${BOUGE_DB_PASSWORD:-bouge}"

echo "→ Base « ${base} » et son utilisateur"
mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`${base}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${utilisateur}'@'%' IDENTIFIED BY '${motdepasse}';
ALTER USER '${utilisateur}'@'%' IDENTIFIED BY '${motdepasse}';
GRANT ALL PRIVILEGES ON \`${base}\`.* TO '${utilisateur}'@'%';
FLUSH PRIVILEGES;
SQL

echo "→ Préparation de la base"
php docker/preparer.php

echo "→ Apache sur le port ${port}"
sed -i "s/^Listen .*/Listen ${port}/" /etc/apache2/ports.conf

exec apache2-foreground
