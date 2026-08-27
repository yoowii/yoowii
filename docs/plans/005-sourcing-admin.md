# Lot 4 — Administration du sourcing print

## Objectif

Permettre à un administrateur Yoowii de configurer le sourcing multi-fournisseurs sans modifier la base à la main ni exécuter une commande console.

## Périmètre livré

- tableau de bord intégré à l’administration Sylius ;
- création et modification des fournisseurs ;
- création et modification des références fournisseurs ;
- création, activation et désactivation des routes principal/secours ;
- contrôle des chevauchements pour une même priorité ;
- import CSV atomique des matrices génériques ;
- activation et archivage irréversible des matrices ;
- contrôle des activations concurrentes à date d’effet identique ;
- simulation serveur d’un devis avec fournisseur, coûts et marge ;
- entrée « Sourcing print » dans le menu d’administration.

Les produits print proposés par ce lot sont `PRINT_FLYER` et `PRINT_BUSINESS_CARD`. Ils utilisent le même importeur et le même calculateur génériques. Le registre intégré est volontairement explicite pour garder le MVP simple ; il pourra ensuite être alimenté par des définitions configurables ou des services tagués.

## Routes

Toutes les routes héritent du préfixe Sylius configuré par `sylius_admin.path_name` et du pare-feu d’administration.

| Action | Méthode | Chemin relatif |
|---|---|---|
| Tableau de bord | GET | `/print-sourcing` |
| Fournisseur | GET, POST | `/print-sourcing/suppliers/new` |
| Référence fournisseur | GET, POST | `/print-sourcing/supplier-products/new` |
| Route | GET, POST | `/print-sourcing/routes/new` |
| Activation route | POST | `/print-sourcing/routes/{id}/toggle` |
| Import matrice | GET, POST | `/print-sourcing/matrices/import` |
| Activation matrice | POST | `/print-sourcing/matrices/{id}/activate` |
| Archivage matrice | POST | `/print-sourcing/matrices/{id}/archive` |
| Simulation | GET, POST | `/print-sourcing/preview` |

## Règles de sécurité et d’intégrité

- accès réservé à `ROLE_ADMINISTRATION_ACCESS` par la configuration Sylius existante ;
- formulaires Symfony et jetons CSRF sur toutes les mutations ;
- import limité à 5 Mo, UTF-8, et annulé entièrement en cas de ligne invalide ;
- aucune version existante n’est remplacée ;
- une matrice archivée ne peut pas être réactivée ;
- une seule route active par produit, priorité et période ;
- une seule matrice active par produit, référence, devise et date d’effet ;
- coûts fournisseur et marge affichés uniquement dans la simulation d’administration.

Un test fonctionnel vérifie que le tableau de bord redirige un visiteur anonyme vers la connexion administrateur et que les routes de mutation refusent la méthode `GET`.

## Vérification locale

```bash
docker compose run --rm php bin/console cache:clear
docker compose run --rm php bin/console debug:router | grep yoowii_admin_sourcing
docker compose run --rm php bin/console lint:twig templates/admin/sourcing
docker compose run --rm php bin/console lint:container
docker compose run --rm php bin/phpunit tests/Yoowii
docker compose run --rm php vendor/bin/phpstan analyse
docker compose run --rm php bin/console doctrine:schema:validate
```

Aucune migration n’est introduite par ce lot. Les tables proviennent des lots multi-fournisseurs déjà appliqués.

## Recette manuelle

1. Créer un fournisseur actif en mode `matrix`.
2. Créer une référence fournisseur active.
3. Créer une route de priorité `10` pour `PRINT_FLYER`.
4. Vérifier qu’une seconde route active de priorité `10` sur la même période est refusée.
5. Importer `docs/examples/flyer-pricing-matrix.csv` dans une nouvelle version et l’activer.
6. Ouvrir la simulation, conserver la configuration flyer proposée et calculer le tarif.
7. Vérifier le fournisseur, la version de matrice, le prix de vente et la marge.
8. Archiver la matrice et vérifier qu’elle n’est plus sélectionnée par le calculateur.

## Hors périmètre

- appel des API Laboprint, Realisaprint, WIRmachenDRUCK ou 123imprim ;
- passage automatique d’une commande fournisseur ;
- comparaison dynamique des délais et frais de livraison ;
- écran de mapping entre les options Yoowii et les codes propres à chaque API ;
- exposition des coûts au storefront.
