# Plan 004 — Moteur générique de tarification print

## Objectif

Remplacer le cœur spécifique aux flyers par un moteur piloté par des définitions de produits, sans casser la commande d'import ni les matrices flyer livrées dans le lot précédent.

## Architecture

### Définition de produit

`PrintProductDefinition` décrit :

- le code commercial Yoowii ;
- la version du schéma ;
- la stratégie tarifaire ;
- les options et leurs types ;
- l'ordre des axes formant la clé de matrice.

Deux définitions intégrées servent de référence :

- `PRINT_FLYER` ;
- `PRINT_BUSINESS_CARD`, avec l'axe supplémentaire `corners`.

### Configuration

`PrintConfiguration` contient les options normalisées et construit une clé stable selon l'ordre déclaré par le schéma. Le snapshot conserve désormais le code produit, la version du schéma et les options.

### Calcul

`MatrixPrintPriceCalculator` est partagé par tous les produits utilisant `matrix_exact`. Il conserve le routage principal/secours, la sélection de version, le calcul de marge et la séparation entre prix client et coût fournisseur.

### Import

`PrintPricingMatrixCsvImporter` construit ses colonnes attendues depuis la définition du produit. Il n'existe donc plus de liste d'en-têtes codée en dur pour chaque calculateur.

La façade `FlyerPricingMatrixCsvImporter` et la commande `yoowii:sourcing:import-flyer-matrix` restent disponibles pour compatibilité. Elles délèguent au moteur générique.

## Format des nouvelles matrices

```json
{
  "schema_version": 1,
  "calculator": "print.matrix_exact",
  "product_code": "PRINT_BUSINESS_CARD",
  "product_schema_version": "1",
  "pricing_axes": ["format", "sides", "paper", "grammage", "quantity", "finishing", "corners"],
  "entries": {}
}
```

Les anciennes matrices portant `calculator: print.flyer` restent acceptées pour `PRINT_FLYER`. Elles pourront être archivées naturellement lors d'un prochain import, sans migration destructive.

## Ajouter un produit matriciel

Pour ajouter un dépliant ou une affiche :

1. déclarer sa `PrintProductDefinition` ;
2. préciser ses options et axes tarifaires ;
3. produire le modèle CSV correspondant ;
4. importer une matrice par fournisseur ;
5. configurer les routes principales et de secours.

Aucun nouveau calculateur PHP n'est nécessaire.

## Hors périmètre

- persistance et édition des définitions dans l'administration ;
- stratégies `area_based`, `api_quote` et `manual_quote` ;
- endpoint storefront de devis ;
- `PrintJob` et `SourcingSnapshot`.
