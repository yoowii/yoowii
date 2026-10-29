# Migration initiale des données vers la préproduction

Cette procédure migre une fois les données locales vers `preprod.yoowii.com`. Elle ne fait partie d'aucun déploiement GitHub Actions. Les volumes `mysql_data`, `media` et `private_assets` survivent aux changements d'image et ne doivent jamais être supprimés par le pipeline.

> Ne pas exécuter cette procédure sur la production. Ne jamais employer `docker compose down -v`, `doctrine:schema:update --force`, ou une option d'import qui écrase une base non vide.

## État audité

L'environnement local Compose s'appelle `yoowii`. Il utilise MySQL `8.4` (`yoowii-mysql-1`, arrêté au moment de l'audit), Redis `7.4` et les volumes locaux `yoowii_mysql-data` et `yoowii_public-media`. MySQL est publié uniquement localement sur le port hôte `3307`; le compte applicatif local est `root` sans mot de passe, conformément à `MYSQL_ALLOW_EMPTY_PASSWORD=1`. Le nom précis de la base ne peut pas être confirmé tant que le conteneur est arrêté : la configuration de développement attend normalement `sylius_dev`; le script refuse toute ambiguïté et accepte `YOOWII_DB_NAME`.

Les données applicatives sont dans MySQL. Les fichiers persistants sont :

- `public/media` : images Sylius (produits, taxons et catalogue), dans le volume Docker `public-media`;
- `var/private/print` : fichiers client, BAT et rapports métier; il est ignoré par Git mais **doit** être sauvegardé;
- `public/themes/yoowii-theme/media` : ressources de thème versionnées dans Git, à ne pas inclure dans l'archive.

Les caches `public/media/cache`, `var/cache`, `var/log` et les temporaires sont exclus. Le compose de préproduction déclare maintenant `private_assets`, monté uniquement dans PHP à `var/private/print`; nginx ne reçoit que le volume public `media` en lecture seule.

Les contrôles de taille, nombre de tables et état des migrations restent à faire après démarrage volontaire de l'environnement local : tous ses conteneurs étaient arrêtés durant l'audit. Exécuter les commandes de la section « Validation locale » avant l'export et reporter les valeurs dans le ticket de migration.

## Prérequis et garde-fous

- Valider une fenêtre de maintenance et l'autorisation explicite d'exporter les données client.
- Travailler sur la branche et l'image à migrer, avec un tag `YOOWII_IMAGE_TAG` immuable.
- Installer `docker` et Docker Compose v2 localement, puis `ssh`, `scp`, `sha256sum` et `gzip` sur le poste opérateur.
- Préparer `/opt/yoowii/preprod/.env` sur le VPS, avec des secrets propres à la préproduction. Ne le copier ni dans Git ni dans les archives.
- Désactiver avant exposition publique les paiements et fournisseurs réels : `MAILER_DSN=null://null`, aucune clé live Stripe/Adyen/Mollie/PayPal, `YOOWII_REALISAPRINT_ENABLED=0`, `YOOWII_REALISAPRINT_QUOTE_ENABLED=0`, `YOOWII_REALISAPRINT_FTP_ENABLED=0` et `YOOWII_REALISAPRINT_PRESCRIPT_QUICK_PUBLICATION_ENABLED=0`. Les routes/plugins de paiement sont présents dans le code : l'absence de clés actives doit être vérifiée.
- Le proxy Plesk doit viser `127.0.0.1:8086`; ne pas modifier les virtual hosts ni les sites WordPress.

## Validation locale avant export

Démarrer explicitement l'environnement si nécessaire, sans supprimer ses volumes :

```bash
docker compose up -d mysql php
docker compose exec -T mysql mysql -uroot -e 'SELECT VERSION(), CURRENT_USER(); SHOW DATABASES;'
docker compose exec -T mysql mysql -uroot -NBe "SELECT table_schema, COUNT(*), ROUND(SUM(data_length + index_length)/1024/1024,2) FROM information_schema.tables WHERE table_schema='sylius_dev' GROUP BY table_schema"
docker compose exec -T php bin/console doctrine:migrations:status --no-interaction
```

Remplacer `sylius_dev` si `SHOW DATABASES` indique un autre unique schéma Sylius. Ne pas transmettre les sorties contenant des URL de connexion ou secrets.

## Export local

Les scripts ne démarrent aucun service et ne modifient pas les données. Ils produisent des fichiers privés, ignorés par Git, sous `backups/preprod/`.

```bash
chmod +x scripts/preprod/export-local.sh scripts/preprod/export-media.sh
YOOWII_DB_NAME=sylius_dev scripts/preprod/export-local.sh
scripts/preprod/export-media.sh
sha256sum -c backups/preprod/*.sha256
```

`export-local.sh` utilise `mysqldump --single-transaction --routines --events --triggers`; il s'agit d'un export cohérent InnoDB sans verrouillage global intentionnel. Geler les écritures applicatives si des tables non transactionnelles sont détectées. `export-media.sh` archive les deux arbres nécessaires, relativement à `/srv/sylius`.

## Transfert vers le VPS

Depuis le poste local, après avoir vérifié les quatre fichiers produits :

```bash
scp backups/preprod/yoowii-preprod-*.sql.gz{,.sha256} deploy-yoowii@preprod.yoowii.com:/home/deploy-yoowii/releases/
scp backups/preprod/yoowii-preprod-media-*.tar.gz{,.sha256} deploy-yoowii@preprod.yoowii.com:/home/deploy-yoowii/releases/
ssh deploy-yoowii@preprod.yoowii.com 'cd /home/deploy-yoowii/releases && sha256sum -c yoowii-preprod-*.sha256'
```

Si les accolades ne sont pas supportées par le shell, transmettre chaque archive et son `.sha256` séparément. S'assurer qu'une seule paire SQL et une seule paire média, avec le même lot, sont sélectionnées.

## Initialisation et restauration sur le VPS

Les commandes suivantes sont à exécuter manuellement depuis `/opt/yoowii/preprod` et seulement après validation du responsable de migration. Elles ne doivent être ni placées dans GitHub Actions ni automatisées au démarrage.

```bash
cd /opt/yoowii/preprod
docker compose --env-file .env -f compose.preprod.yml up -d mysql redis
docker compose --env-file .env -f compose.preprod.yml ps
docker compose --env-file .env -f compose.preprod.yml exec -T mysql sh -ec 'mysql -uroot --password="$MYSQL_ROOT_PASSWORD" -NBe "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '\''$MYSQL_DATABASE'\''"'
```

Le dernier résultat doit être `0`. Toute autre valeur signifie que la cible contient déjà des données : **arrêter et ne rien importer**. Vérifier aussi que les volumes ont bien les noms de projet attendus :

```bash
docker volume inspect yoowii-preprod_mysql_data yoowii-preprod_media yoowii-preprod_private_assets
```

Importer ensuite l'archive SQL déjà vérifiée. Cette archive ne crée pas la base : elle est importée dans la base initialisée par MySQL Compose.

```bash
gzip -dc /home/deploy-yoowii/releases/yoowii-preprod-YYYYMMDD-HHMMSS.sql.gz | docker compose --env-file .env -f compose.preprod.yml exec -T mysql sh -ec 'exec mysql -uroot --password="$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

Initialiser les permissions des volumes applicatifs, puis démarrer PHP. L'UID `82` est celui de `www-data` dans l'image Alpine utilisée; confirmer avec `docker compose ... run --rm php id www-data` avant de le faire si l'image a changé.

```bash
docker run --rm -u root -v yoowii-preprod_media:/media -v yoowii-preprod_private_assets:/private alpine:3.20 sh -ec 'mkdir -p /media /private && chown -R 82:82 /media /private'
docker compose --env-file .env -f compose.preprod.yml up -d php
```

Refuser les archives inattendues avant extraction :

```bash
tar -tzf /home/deploy-yoowii/releases/yoowii-preprod-media-YYYYMMDD-HHMMSS.tar.gz | grep -E '^(public/media|var/private/print)(/|$)' >/dev/null
```

Le volume vient d'être créé et est donc vide; cette extraction est l'unique import autorisé. Elle ne doit jamais être rejouée sur un volume contenant des fichiers.

```bash
docker compose --env-file .env -f compose.preprod.yml exec -T php sh -ec 'test -z "$(find public/media var/private/print -mindepth 1 -print -quit)"'
docker compose --env-file .env -f compose.preprod.yml exec -T php tar -xzf - -C /srv/sylius < /home/deploy-yoowii/releases/yoowii-preprod-media-YYYYMMDD-HHMMSS.tar.gz
docker compose --env-file .env -f compose.preprod.yml up -d nginx
```

## Vérifications après import

```bash
docker compose --env-file .env -f compose.preprod.yml exec -T php bin/console doctrine:migrations:status --no-interaction
docker compose --env-file .env -f compose.preprod.yml exec -T mysql sh -ec 'mysql -uroot --password="$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -NBe "SELECT '\''products'\'', COUNT(*) FROM sylius_product UNION ALL SELECT '\''variants'\'', COUNT(*) FROM sylius_product_variant UNION ALL SELECT '\''taxons'\'', COUNT(*) FROM sylius_taxon UNION ALL SELECT '\''channels'\'', COUNT(*) FROM sylius_channel UNION ALL SELECT '\''realisaprint catalog'\'', COUNT(*) FROM yoowii_realisaprint_catalog_product UNION ALL SELECT '\''supplier mappings'\'', COUNT(*) FROM yoowii_supplier_product_mapping_version"'
docker compose --env-file .env -f compose.preprod.yml exec -T php sh -ec 'find public/media var/private/print -type f | wc -l'
curl --fail --silent --show-error -I https://preprod.yoowii.com/
```

Comparer les comptes avec les valeurs locales relevées avant export. Dans la boutique, contrôler une page catalogue, les images produit, la navigation taxonomique et une configuration Prescript sans publication ni commande. Confirmer depuis la préproduction que le transport mail est `null://null`, que les passerelles de paiement n'ont pas de clés live et que Realisaprint/FTP sont désactivés.

## Failure recovery and future deployments

If checksum, import or application validation fails, stop the preproduction application with `docker compose ... stop php nginx`; preserve the failed logs and archives. Do not retry over the same data. Remove/recreate only the specifically empty initialisation volumes after explicit approval, then repeat from checksum validation. Never use `down -v`.

Future deployment jobs may pull immutable images and run migrations only after backup and approval. They must not mount over, copy into, prune, create, or remove `mysql_data`, `media`, or `private_assets`; no volume flag belongs in CI/CD.
