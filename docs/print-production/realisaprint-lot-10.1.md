# Lots 10.1–10.2 — Transmission et suivi Realisaprint

Le connecteur prépare et transmet une commande Realisaprint uniquement pour un dossier affecté à `realisaprint`, avec un BAT approuvé et un préflight réussi.

## Sécurité

Par défaut, `YOOWII_REALISAPRINT_ENABLED=0`. Dans ce mode, l'action d'administration produit une simulation persistée : aucun appel HTTP et aucune commande fournisseur ne sont effectués. Le dossier reste à l'état `bat_approved`.

Chaque dossier possède une clé d'idempotence stable (`realisaprint:PJ-…`). Une commande déjà confirmée ne peut pas être transmise une seconde fois. Les tentatives en échec et simulations peuvent être relancées depuis le dossier.

Les identifiants API ne sont jamais journalisés. Le contenu du fichier client reste privé ; le transfert FTP est tracé séparément et ne peut pas dupliquer un fichier déjà déposé.

## Installation

```bash
docker compose run --rm php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm php bin/console cache:clear
```

Ajoute les accès fournis par Realisaprint uniquement dans l'environnement cible :

```dotenv
YOOWII_REALISAPRINT_ENABLED=0
YOOWII_REALISAPRINT_BASE_URL=https://www.realisaprint.com/api
YOOWII_REALISAPRINT_SHOP_ID=
YOOWII_REALISAPRINT_API_KEY=
YOOWII_REALISAPRINT_FTP_ENABLED=0
YOOWII_REALISAPRINT_FTP_HOST=
YOOWII_REALISAPRINT_FTP_PORT=21
YOOWII_REALISAPRINT_FTP_USER=
YOOWII_REALISAPRINT_FTP_PASSWORD=
```

L'URL et les paramètres définitifs doivent être validés avec l'accès revendeur avant de mettre `ENABLED=1`.

## Recette sans accès API

1. Créer une commande dont le fournisseur retenu est `realisaprint`.
2. Déposer un fichier client conforme, puis publier et valider le BAT.
3. Dans **Administration → Production print**, ouvrir le dossier et cliquer **Transmettre à Realisaprint**.
4. Vérifier le message de simulation, la tentative dans l'historique et l'absence de passage à `in_production`.

## Mapping Print Sourcing

Une soumission API n'est permise que si le `PrintSupplier` est actif, en mode `api` ou `hybrid`, et possède la capacité `order_submission`.

La version active du mapping fournisseur doit contenir un bloc immuable de cette forme :

```json
{
  "realisaprint": {
    "product": "243",
    "stock": "988",
    "variables": {
      "VARTICLE_21050_": {"option": "format", "values": {"A5": "2", "A6": "3"}},
      "VARTICLE_21190_": {"option": "quantity"}
    }
  }
}
```

## Lot 10.2

- appel `save_configuration` avant `create_order` ;
- dépôt FTP dans l'arborescence renvoyée par Realisaprint ;
- synchronisation manuelle/planifiée des statuts et du suivi avec `yoowii:print-jobs:sync-realisaprint` ;
- sécurité : envoi automatique uniquement si Realisaprint attend exactement un fichier. Un recto/verso ou plusieurs fichiers reste manuel.

## Limites restantes

La configuration exacte de chaque produit, les accès FTP et la validation réelle des statuts nécessitent toujours l'accès revendeur. Les fichiers multi-zones, recto-verso et les annulations fournisseur restent contrôlés manuellement.
