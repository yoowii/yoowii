# Lot 5 — Calculateur print du storefront

## Objectif

Permettre au client de configurer un flyer ou une carte de visite, d’obtenir un prix calculé côté serveur puis d’ajouter cette configuration au panier Sylius sans pouvoir modifier le montant.

## Flux livré

```text
Matrices actives
  -> options disponibles
  -> formulaire Symfony + CSRF
  -> validation par PrintProductDefinition
  -> calcul multi-fournisseurs côté serveur
  -> devis temporaire en session
  -> ajout au panier
  -> PricingSnapshot sur OrderItem
  -> recalcul du panier Sylius
```

Le fournisseur retenu et ses coûts restent internes. Le storefront ne reçoit que le prix client, la devise et la configuration commerciale.

## Préparation du catalogue Sylius

Créer ou vérifier deux produits simples dans le canal de vente :

| Produit | Code de la variante par défaut | Type d’exécution |
|---|---|---|
| Flyers | `PRINT_FLYER` | `print` |
| Cartes de visite | `PRINT_BUSINESS_CARD` | `print` |

Les produits et variantes doivent être activés et rattachés au canal courant. Leur prix Sylius générique n’est pas utilisé par le calculateur : le snapshot serveur fixe le prix personnalisé de la ligne.

Le prix calculé est un prix hors taxes. La catégorie fiscale du produit laisse Sylius calculer la TVA dans le panier. Comme la matrice fournisseur contient déjà le coût de livraison, le mode d’expédition print du MVP doit être un mode à `0` intitulé par exemple « Livraison incluse », afin de ne pas facturer deux fois le transport.

## Routes boutique

Les routes sont localisées comme le storefront Sylius :

| Action | Méthode | Chemin relatif |
|---|---|---|
| Catalogue print | GET | `/{_locale}/print` |
| Configurer un produit | GET, POST | `/{_locale}/print/{productCode}` |
| Ajouter le devis au panier | POST | `/{_locale}/print/quote/{token}/cart` |

Exemples :

```text
/fr_FR/print
/fr_FR/print/PRINT_FLYER
/fr_FR/print/PRINT_BUSINESS_CARD
```

## Sécurité et intégrité

- le navigateur ne poste jamais de prix ;
- la définition serveur rejette les options inconnues ou invalides ;
- les combinaisons absentes des matrices sont refusées ;
- le devis utilise un jeton aléatoire de 256 bits ;
- le devis est lié à la session, expire après 900 secondes et n’est consommable qu’une fois ;
- le code variante du devis doit correspondre au code produit du snapshot ;
- l’ajout utilise le panier issu de `CartContextInterface`, jamais un identifiant de panier fourni par le client ;
- la variante, le produit, le canal, le type `print` et la devise sont contrôlés ;
- la TVA reste calculée par les règles fiscales Sylius ;
- une ligne service ou abonnement déjà présente bloque l’ajout d’un produit print ;
- la mutation est exclusivement en `POST` et protégée par CSRF ;
- le snapshot rend le prix de la ligne immuable pour les recalculs Sylius.

## Politique commerciale

Les paramètres initiaux sont déclarés dans `config/services.yaml` :

```yaml
yoowii.print_pricing.version: 'retail-v1'
yoowii.print_pricing.markup_basis_points: 3500
yoowii.print_pricing.minimum_margin: 1200
yoowii.print_pricing.handling_fee: 0
yoowii.print_quote.ttl: 900
```

Les montants sont exprimés en centimes et la majoration en points de base. Il faut changer la version de politique lorsqu’une règle commerciale change afin de conserver une piste d’audit lisible dans le snapshot.

## Limite volontaire du MVP

Les valeurs de chaque axe sont extraites dynamiquement des matrices, mais les listes ne sont pas encore dépendantes entre elles dans le navigateur. Une combinaison théoriquement proposée mais absente de la matrice produit un message « aucun tarif disponible ». Le prochain raffinement pourra filtrer les choix en cascade sans modifier le moteur de prix.

## Vérification locale

```bash
docker compose run --rm php bin/console cache:clear
docker compose run --rm php bin/console debug:router | grep yoowii_shop_print
docker compose run --rm php bin/console lint:twig templates/shop/print
docker compose run --rm php bin/console lint:container
docker compose run --rm php bin/phpunit tests/Yoowii tests/Entity
docker compose run --rm php vendor/bin/phpstan analyse
docker compose run --rm php vendor/bin/ecs check
docker compose run --rm php bin/console doctrine:schema:validate
```

Aucune migration Doctrine n’est nécessaire pour ce lot.

## Recette manuelle

1. Vérifier les variantes et leur type d’exécution dans Sylius.
2. Activer au moins une route et une matrice EUR depuis `/admin/print-sourcing`.
3. Ouvrir `/fr_FR/print/PRINT_FLYER` et calculer un prix.
4. Modifier le HTML côté navigateur et confirmer qu’aucun champ de prix n’existe à falsifier.
5. Ajouter au panier et vérifier le prix ainsi que la configuration du snapshot en base.
6. Réutiliser le même formulaire d’ajout et vérifier que le devis est refusé.
7. Créer un devis, attendre son expiration puis vérifier son refus.
8. Vérifier que deux configurations créent deux lignes séparées.
