# Lot 5 — Catalogue Sylius et fiche produit print

## Objectif

Afficher les produits print et non-print dans le catalogue Sylius unique, personnaliser uniquement l’expérience des produits `print`, puis ajouter leur configuration calculée côté serveur au panier Sylius.

## Flux livré

```text
Produit Sylius du catalogue
  -> fiche produit Sylius standard
  -> définition print associée
  -> matrices actives
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

## Modèle catalogue

Il n’existe pas de second catalogue technique. Chaque produit print est un produit Sylius classique avec ses traductions, images, taxons, attributs, associations, canaux et une variante commerciale.

| Donnée | Rôle |
|---|---|
| `Product::fulfillmentType` | Active l’expérience print quand sa valeur est `print` |
| `Product::printDefinitionCode` | Sélectionne le schéma de calculateur et ses matrices |
| Prix de la variante par canal | Prix marketing affiché sous la forme « À partir de » |
| `PricingSnapshot` | Prix réellement calculé et verrouillé dans le panier |

Créer ou vérifier deux produits simples dans le canal de vente :

| Produit | Exemple de variante | Type | Définition |
|---|---|---|---|
| Flyers | `FLYER_STANDARD` | `print` | `PRINT_FLYER` |
| Cartes de visite | `BUSINESS_CARD_STANDARD` | `print` | `PRINT_BUSINESS_CARD` |

Les codes des variantes et des calculateurs sont volontairement distincts. Les produits et variantes doivent être activés et rattachés au canal courant. Le prix Sylius sert uniquement de prix de départ dans le catalogue ; le snapshot serveur fixe le montant réellement commandé.

## Expérience storefront

Les cartes du catalogue Sylius restent inchangées pour les produits standards. Pour un produit print, le composant prix ajoute « À partir de » devant le prix de canal.

La fiche conserve la route, les images, le titre, la référence, les descriptions, attributs, avis et produits associés de Sylius. Le formulaire standard des variantes est remplacé par un bouton d’ancrage, puis un calculateur pleine largeur est injecté entre les informations produit et les produits associés :

```text
Options en accordéon       Récapitulatif sticky
- format                   - choix retenus
- impression               - prix calculé
- papier                   - devise
- grammage                 - ajout au panier
- quantité
- finition
```

Sur mobile, le récapitulatif repasse sous les options. L’ancienne URL `/print/{productCode}` redirige vers la fiche Sylius canonique. La landing `/print` est conservée, mais elle charge uniquement des produits Sylius actifs du canal courant.

Le prix calculé est un prix hors taxes. La catégorie fiscale du produit laisse Sylius calculer la TVA dans le panier. Comme la matrice fournisseur contient déjà le coût de livraison, le mode d’expédition print du MVP doit être un mode à `0` intitulé par exemple « Livraison incluse », afin de ne pas facturer deux fois le transport.

## Routes boutique

Les routes sont localisées comme le storefront Sylius :

| Action | Méthode | Chemin relatif |
|---|---|---|
| Catalogue print | GET | `/{_locale}/print` |
| Ancienne URL de configuration | GET, redirection | `/{_locale}/print/{productCode}` |
| Calculer depuis la fiche | POST | `/{_locale}/products/{productCode}/print-quote` |
| Ajouter le devis au panier | POST | `/{_locale}/print/quote/{token}/cart` |

Exemples :

```text
/fr_FR/print
/fr_FR/products/flyers
/fr_FR/products/FLYER_STANDARD/print-quote
```

## Sécurité et intégrité

- le navigateur ne poste jamais de prix ;
- la définition serveur rejette les options inconnues ou invalides ;
- les combinaisons absentes des matrices sont refusées ;
- le devis utilise un jeton aléatoire de 256 bits ;
- le devis est lié à la session, expire après 900 secondes et n’est consommable qu’une fois ;
- le devis distingue le code de variante Sylius du code de définition print ;
- la définition du devis doit correspondre au `product_code` du snapshot ;
- la variante du devis doit appartenir au produit Sylius affiché ;
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
docker compose run --rm php bin/console lint:twig templates/shop
docker compose run --rm php bin/console lint:container
docker compose run --rm php bin/phpunit tests/Yoowii tests/Entity
docker compose run --rm php vendor/bin/phpstan analyse
docker compose run --rm php vendor/bin/ecs check
docker compose run --rm php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm php bin/console doctrine:schema:validate
```

La migration `Version20260828180000` ajoute le lien entre le produit Sylius et la définition du calculateur. Elle reprend automatiquement les anciens produits dont le code produit ou variante est `PRINT_FLYER` ou `PRINT_BUSINESS_CARD`.

## Recette manuelle

1. Exécuter la migration et renseigner le type `print`, la définition et le prix de départ dans Sylius.
2. Activer au moins une route et une matrice EUR depuis `/admin/print-sourcing`.
3. Ouvrir le catalogue Sylius et vérifier la présence simultanée de produits print et non-print.
4. Vérifier « À partir de » uniquement sur les cartes print.
5. Ouvrir la fiche Sylius du flyer et calculer un prix dans le configurateur pleine largeur.
6. Modifier le HTML côté navigateur et confirmer qu’aucun champ de prix n’existe à falsifier.
7. Ajouter au panier et vérifier le prix ainsi que la configuration du snapshot en base.
8. Réutiliser le même formulaire d’ajout et vérifier que le devis est refusé.
9. Créer un devis, attendre son expiration puis vérifier son refus.
10. Vérifier que deux configurations créent deux lignes séparées.
