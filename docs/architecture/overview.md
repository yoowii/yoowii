# Architecture de Yoowii

## Objectif

Yoowii commercialise des produits imprimés et des prestations numériques, puis suit leur réalisation dans Yoowii Flow. Le produit démarre comme un monolithe modulaire afin de conserver un déploiement et un modèle d'authentification uniques.

## Responsabilités

| Contexte | Responsabilités |
|---|---|
| Sylius Catalog | Produits, taxons, attributs, variantes et canaux |
| Sylius Order | Panier, commande, promotions, taxes et totalisation |
| Sylius Customer | Compte client et adresses |
| Sylius Payment | Demandes et états de paiement |
| Sylius Shipping | Modes, frais et expéditions physiques |
| Pricing | Matrices tarifaires et calcul serveur |
| Sourcing | Fournisseurs print, mappings, grilles d'achat et routage principal/secours |
| Configurator | Parcours et choix propres à une famille de produits |
| PrintProduction | Fichiers, BAT, production, contrôle et livraison |
| WebProject | CDC, prototype, intégration, recette et mise en production |
| MediaProject | Brief, planification, shooting, montage et livraison |
| Subscription | Contrats et cycles récurrents |
| CustomerPortal | Vue client des commandes, projets, validations et documents |
| Automation | Mantis, Plesk, LWS, WP-CLI et fournisseurs externes |

## Flux principal

```text
Configuration
  -> calcul du prix côté serveur
  -> snapshot tarifaire immuable
  -> panier Sylius
  -> checkout et paiement
  -> événement OrderPaid
  -> plan d'exécution par ligne
  -> création idempotente des travaux
  -> jalons visibles dans le portail client
```

## Types d'exécution

Chaque produit commercial définit un type d'exécution :

- `print` : crée un travail d'impression ;
- `web_project` : crée un projet de création ou refonte de site ;
- `media_project` : crée un projet photo ou vidéo ;
- `subscription` : crée ou rattache un contrat récurrent ;
- `quote_only` : crée une demande de devis sans checkout direct.

`quote_only` est la valeur par défaut. Ce choix empêche un produit existant, importé ou de démonstration de déclencher une production tant que son type d’exécution n’a pas été validé dans l’administration.

Une commande peut contenir plusieurs produits d'impression. Le MVP utilise des checkouts distincts pour l'impression, les services et les abonnements afin d'éviter les ambiguïtés de livraison, de remboursement et de renouvellement.

## Calcul tarifaire

Les choix affichés par le navigateur ne constituent jamais la source de vérité. Le serveur reçoit la configuration, la valide, charge une version de matrice tarifaire, calcule le détail et renvoie un devis temporaire signé ou identifié.

La ligne de commande conserve au minimum :

```json
{
  "schema_version": 1,
  "calculator": "print.flyer",
  "pricing_version": "2026-08-01",
  "configuration": {},
  "price_breakdown": {
    "total": 12900
  },
  "unit_price": 12900,
  "currency_code": "EUR",
  "calculated_at": "2026-08-27T12:00:00+00:00"
}
```

Les montants utilisent l’unité monétaire mineure : `12900` représente `129,00 EUR`. Le total du détail doit correspondre au prix unitaire de la ligne et la devise doit correspondre à celle du panier.

Le snapshot reste remplaçable tant que la commande est un panier. Une fois le checkout terminé, l’entité refuse toute modification. La ligne est également marquée comme prix personnalisé afin que le recalculateur Sylius ne remplace pas son montant par le prix générique de la variante.

Deux lignes configurées ne sont jamais fusionnées automatiquement : la quantité d’impression appartient à la configuration (`1 000 flyers`) et non à la quantité Sylius de la ligne.

## Sourcing multi-fournisseurs

Le catalogue vendu au client reste indépendant des références Laboprint, Realisaprint, WIRmachenDRUCK ou 123imprim. Un `SupplierProduct` représente la référence technique d'un imprimeur. Ses options sont traduites depuis la configuration Yoowii par un `SupplierProductMappingVersion` immuable.

Chaque grille de coût est importée dans un nouveau `SupplierPricingMatrixVersion`. Une version suit le cycle `draft -> active -> archived` et une version archivée ne peut pas être réactivée. Son checksum permet de détecter un import identique, indépendamment de l'ordre des clés JSON.

Le MVP applique un routage fixe par code produit Yoowii : priorité `10` pour le fournisseur principal, puis `20`, `30`, etc. pour les secours. Le routeur refuse une égalité de priorité et ignore les routes hors période ainsi que les fournisseurs ou produits désactivés. Aucun appel API fournisseur n'est effectué dans ce premier lot.

Le prix de vente et le sourcing restent séparés :

- `PricingSnapshot` sur `OrderItem` conserve le prix client ;
- un futur `SourcingSnapshot` sur le travail d'impression conservera le fournisseur, la référence, la grille de coût et la règle de routage retenus ;
- le coût d'achat ne sera jamais exposé par les endpoints du storefront.

Les produits matriciels utilisent un moteur commun piloté par `PrintProductDefinition`. Chaque définition déclare ses options, leur type et l'ordre des axes tarifaires. Les flyers et cartes de visite partagent ainsi `PrintPricingMatrixCsvImporter` et `MatrixPrintPriceCalculator` sans dupliquer le calcul.

Le calculateur classe les routes fixes, charge les versions actives et effectives, puis passe à la route de secours si la combinaison n'existe pas chez le fournisseur principal. La politique commerciale est exprimée séparément en majoration, marge minimale et frais de traitement.

Les nouvelles matrices utilisent `calculator: print.matrix_exact` et identifient le code produit ainsi que la version de son schéma. Les anciennes matrices `print.flyer` restent lisibles pour assurer une évolution non destructive.

## Fichiers clients

Les fichiers d'impression, briefs et contenus ne doivent pas être placés dans le répertoire public. Le stockage cible est compatible S3 avec URL signée, contrôle de type/taille, antivirus et journal d'accès. Le stockage local peut être utilisé uniquement en développement.

## Évolution

Redis, un frontend headless, un service de préflight PDF ou des services séparés ne seront ajoutés qu'à partir d'une contrainte mesurée. Les frontières des modules permettent cette extraction sans introduire cette complexité dès le MVP.
