# Lot 10.1 — Transmission Realisaprint

Le connecteur prépare et transmet une commande Realisaprint uniquement pour un dossier affecté à `realisaprint`, avec un BAT approuvé et un préflight réussi.

## Sécurité

Par défaut, `YOOWII_REALISAPRINT_ENABLED=0`. Dans ce mode, l'action d'administration produit une simulation persistée : aucun appel HTTP et aucune commande fournisseur ne sont effectués. Le dossier reste à l'état `bat_approved`.

Chaque dossier possède une clé d'idempotence stable (`realisaprint:PJ-…`). Une commande déjà confirmée ne peut pas être transmise une seconde fois. Les tentatives en échec et simulations peuvent être relancées depuis le dossier.

Les identifiants API ne sont jamais journalisés. Le contenu du fichier client reste privé ; le lot 10.1 conserve seulement son nom et son checksum. Le transfert FTP du BAT/fichier est volontairement reporté au lot 10.2.

## Installation

```bash
docker compose run --rm php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm php bin/console cache:clear
```

Ajoute les accès fournis par Realisaprint uniquement dans l'environnement cible :

```dotenv
YOOWII_REALISAPRINT_ENABLED=0
YOOWII_REALISAPRINT_BASE_URL=https://api.realisaprint.com
YOOWII_REALISAPRINT_SHOP_ID=
YOOWII_REALISAPRINT_API_KEY=
```

L'URL et les paramètres définitifs doivent être validés avec l'accès revendeur avant de mettre `ENABLED=1`.

## Recette sans accès API

1. Créer une commande dont le fournisseur retenu est `realisaprint`.
2. Déposer un fichier client conforme, puis publier et valider le BAT.
3. Dans **Administration → Production print**, ouvrir le dossier et cliquer **Transmettre à Realisaprint**.
4. Vérifier le message de simulation, la tentative dans l'historique et l'absence de passage à `in_production`.

## Limites du lot

La correspondance exacte des produits/options Realisaprint et le transfert FTP des fichiers nécessitent les accès revendeur : ils font partie du lot 10.2 avec la synchronisation des statuts et du suivi.
