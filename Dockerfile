# ---------------------------------------------------------------------------
# Image de démonstration pour Render
#
# La boutique tourne normalement sur un hébergement mutualisé : Apache, PHP et
# une base MySQL fournie par l'hébergeur. Render ne propose pas de MySQL — leur
# base managée est PostgreSQL. Pour une démonstration, plutôt que de réécrire
# toutes les requêtes, l'image embarque MariaDB à côté d'Apache.
#
# Ce que cela implique, et qu'il faut savoir avant de s'en servir :
#
#   - Les données ne survivent pas à un redémarrage. Sur l'offre gratuite, le
#     service s'endort après quinze minutes sans visite et repart d'une image
#     neuve. Pour une démonstration, c'est un avantage : le catalogue est
#     toujours propre, quoi qu'ait fait le visiteur précédent.
#   - Ce n'est PAS la façon de mettre la boutique en production. Pour cela,
#     voir la section « Mettre en ligne chez OVH » du README : une base
#     séparée, sauvegardée, et des fichiers qui restent.
# ---------------------------------------------------------------------------

FROM php:8.3-apache

# pdo_mysql est la seule extension à ajouter : mbstring, curl, fileinfo et
# iconv sont déjà compilées dans l'image officielle.
#
# Le php.ini de production est mis en place au passage : sans lui, PHP affiche
# ses erreurs à l'écran — chemins du serveur et trace d'appels compris. Sur une
# adresse publique, elles doivent aller au journal, pas au visiteur.
#
# Pas de commentaire à l'intérieur du RUN : les continuations de ligne en font
# une seule commande, et un « # » y masquerait tout ce qui suit.
RUN set -eux; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        mariadb-server mariadb-client; \
    rm -rf /var/lib/apt/lists/*; \
    a2enmod rewrite headers; \
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Le domaine du site est le dossier public/ : le reste du code — la
# configuration, les gabarits, le schéma — n'est jamais servi.
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/mariadb.cnf /etc/mysql/conf.d/bouge-demo.cnf

WORKDIR /var/www/html
COPY . .

# Le dossier des photos est le seul où le site écrit.
RUN set -eux; \
    chown -R www-data:www-data public/uploads; \
    chmod +x docker/entrypoint.sh

# Render fournit le port à écouter dans $PORT ; 10000 est sa valeur par défaut.
EXPOSE 10000

ENTRYPOINT ["docker/entrypoint.sh"]
