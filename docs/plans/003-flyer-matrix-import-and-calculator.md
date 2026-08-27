# Plan 003 — Import de matrices et calculateur flyer

## Objectif

Importer une grille fournisseur normalisée sans écriture partielle, puis calculer côté serveur le prix de vente d'un flyer en appliquant le routage principal/secours et la politique commerciale Yoowii.

## Contrat CSV canonique

Le fichier est encodé en UTF-8 et utilise la virgule ou le point-virgule. Il contient exactement ces colonnes, dans n'importe quel ordre :

```text
format;sides;paper;grammage;quantity;finishing;production_cost;shipping_cost
```

Les valeurs `format`, `paper` et `finishing` sont des codes canoniques en minuscules. `sides` accepte uniquement `one_sided` ou `two_sided`. Les montants sont des entiers en centimes, jamais des nombres décimaux.

Exemple disponible dans `docs/examples/flyer-pricing-matrix.csv`.

## Protections de l'import

- taille maximale : 5 000 000 octets ;
- maximum : 50 000 lignes de prix ;
- UTF-8 obligatoire et octets NUL interdits ;
- en-têtes manquants, inconnus ou dupliqués refusés ;
- nombres entiers et valeurs positives contrôlés ;
- doublon d'une combinaison flyer refusé ;
- maximum de 100 erreurs retournées ;
- construction de l'entité seulement après validation de toutes les lignes.

## Commande d'import

```bash
docker compose run --rm php bin/console yoowii:sourcing:import-flyer-matrix \
  laboprint \
  FLYER_STANDARD \
  2026-09-01 \
  2026-09-01 \
  docs/examples/flyer-pricing-matrix.csv \
  --currency=EUR \
  --activate
```

Sans `--activate`, la nouvelle matrice reste en brouillon. Une version existante n'est jamais remplacée.

## Calcul du prix de vente

La politique utilise un taux de majoration en points de base, un minimum de marge et des frais de traitement :

```text
coût fournisseur = coût de production + coût de livraison
marge = max(arrondi supérieur(coût fournisseur × taux de majoration), marge minimale)
prix de vente = coût fournisseur + marge + frais de traitement
```

`3500` points de base représentent une majoration de 35 % sur le coût. Il ne s'agit pas d'un taux de marge sur le prix de vente.

Le calculateur :

1. classe les routes par priorité ;
2. cherche la matrice active la plus récente du fournisseur principal ;
3. cherche la combinaison exacte ;
4. passe au fournisseur de secours si la combinaison est absente ;
5. calcule le prix client ;
6. produit un `PricingSnapshot` ne révélant aucun coût fournisseur.

## Hors périmètre

- transformation automatique des CSV propriétaires des imprimeurs ;
- écran d'administration d'import ;
- appel API fournisseur ;
- devis signé et endpoint storefront ;
- création du `PrintJob` et du `SourcingSnapshot`.
