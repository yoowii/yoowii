# Yoowii

Yoowii est la plateforme e-commerce et de suivi de production de Yoowii. Elle vend des produits d'impression, des packs web, des prestations photo/vidéo et des abonnements, puis transforme chaque ligne de commande payée en travail suivi dans Yoowii Flow.

## Socle technique

- PHP 8.3
- Symfony 7.4
- Sylius 2.2
- MySQL 8.4
- Twig et composants Sylius pour le storefront et l'administration
- React uniquement pour les calculateurs ou interfaces qui le justifient
- Symfony Messenger pour les traitements asynchrones
- PHPUnit, PHPStan niveau 9 et ECS

## Démarrage local

Prérequis : Docker avec le plugin Docker Compose et `make`.

```bash
make init
```

Après l'installation :

- boutique : <http://localhost/>
- administration : <http://localhost/admin>
- MailHog : <http://localhost:8025>

Commandes principales :

```bash
make up
make down
make test
make phpstan
make cs
make quality
```

`make clean` supprime les conteneurs et les volumes locaux. Il ne doit être utilisé que si la réinitialisation de la base est volontaire.

## Architecture

Sylius reste responsable du catalogue, des clients, du panier, des commandes, des promotions, des paiements et de la livraison. Les fonctionnalités différenciantes résident dans les modules `App\Yoowii` :

```text
src/Yoowii/
├── Commerce/
├── Pricing/
├── Configurator/
├── PrintProduction/
├── WebProject/
├── MediaProject/
├── Subscription/
├── CustomerPortal/
└── Automation/
```

Une commande commerciale et un travail de production sont deux concepts distincts. Une commande payée publie un événement idempotent qui crée un `PrintJob`, un `WebProject`, un `MediaProject` ou un `SubscriptionContract` selon chaque ligne.

Consulter :

- [vue d'ensemble de l'architecture](docs/architecture/overview.md) ;
- [décision d'utiliser Sylius](docs/decisions/001-sylius-modular-monolith.md) ;
- [plan du premier incrément](docs/plans/001-commerce-foundation.md).

## État du chantier

Le dépôt contient le socle Sylius officiel, le typage d’exécution des produits et le snapshot tarifaire immuable des lignes de commande. Le prochain incrément est le premier calculateur d’impression consacré aux flyers.
