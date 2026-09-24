# Cotation API Realisaprint

Le fournisseur `realisaprint` doit être actif, en mode `api` ou `hybrid`, et posséder la capacité `realtime_quote`.

Active la cotation uniquement après avoir validé l’accès test :

```dotenv
YOOWII_REALISAPRINT_ENABLED=1
YOOWII_REALISAPRINT_QUOTE_ENABLED=1
YOOWII_REALISAPRINT_QUOTE_CACHE_TTL=900
```

Une route Realisaprint est alors cotée en priorité par l’API. La matrice reste un repli : elle est utilisée lorsqu’un mapping est absent, que le fournisseur n’est pas compatible API ou que Realisaprint ne répond pas.

## Découverte catalogue

```bash
docker compose run --rm php bin/console yoowii:realisaprint:catalog
docker compose run --rm php bin/console yoowii:realisaprint:catalog 243
```

La première commande affiche les produits. La seconde affiche les stocks et variables d’un produit. Le connecteur bloque aussi localement un second appel à la même fonction durant 15 secondes, conformément à la limite Realisaprint.

## Mapping administrable

Dans **Production print → Sourcing**, créer un mapping API versionné pour la référence fournisseur Realisaprint. Exemple minimal pour une configuration flyer :

```json
{
  "realisaprint": {
    "product": "243",
    "stock": "…",
    "variables": {
      "VARTICLE_FORMAT_": {
        "option": "format",
        "values": {"a5": "2", "a6": "3"}
      },
      "VARTICLE_QUANTITY_": {
        "option": "quantity",
        "values": {"100": "100", "250": "250"}
      }
    }
  }
}
```

Les clés des tableaux `values` sont les valeurs canoniques Yoowii. Elles alimentent aussi les choix du configurateur lorsqu’aucune matrice n’est disponible. Pour une variable sans tableau de conversion, fournir `catalog_values`, par exemple `[100, 250, 500]`.

Chaque devis API exécute `save_configuration` puis `get_price`. Le montant HT fournisseur et les éventuels additionnels sont conservés dans le snapshot, puis la politique de marge Yoowii est appliquée côté serveur. Le cache de configuration évite de rappeler l’API pour un même mapping/choix pendant sa durée de vie. Un garde-fou commun bloque tout second appel à la même fonction API durant 15 secondes ; pendant ce délai le moteur utilise une matrice ou une route de secours si elles existent.
