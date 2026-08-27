# Plan 001 — Fondation commerce et exécution

## Objectif

Obtenir un premier parcours vertical testé : configurer un flyer, calculer son prix côté serveur, le commander, confirmer son paiement et créer exactement un travail d'impression visible par le client.

## Hors périmètre

- abonnement ;
- fournisseur d'impression connecté par API ;
- contrôle prépresse avancé ;
- transporteur connecté ;
- éditeur graphique ;
- panier mélangeant produits imprimés et services ;
- automatisation Mantis/Plesk/LWS.

## Étapes

### 0. Socle

- importer Sylius Standard ;
- personnaliser README, règles agents et CI ;
- installer le projet avec Docker ;
- committer les fichiers de verrouillage générés ;
- vérifier storefront, admin et suite qualité.

### 1. Typage de l'exécution

- [x] introduire `FulfillmentType` ;
- [x] rattacher le type au produit avec `quote_only` comme valeur sûre par défaut ;
- [x] ajouter la migration et le champ d'administration ;
- [x] tester les cinq valeurs autorisées et le comportement du produit ;
- [ ] exécuter la migration et les tests dans l'environnement Docker.

### 2. Snapshot de configuration

- étendre la ligne de commande avec un snapshot JSON versionné ;
- interdire sa mutation après finalisation de la commande ;
- ajouter validation, migration et tests.

### 3. Calculateur de flyers

- modéliser formats, faces, papiers, quantités et finitions autorisés ;
- importer une matrice tarifaire versionnée ;
- créer l'endpoint de devis ;
- intégrer le composant au storefront ;
- recalculer systématiquement à l'ajout au panier.

### 4. Paiement

- configurer Stripe en environnement de test ;
- vérifier la signature des webhooks ;
- rendre leur traitement idempotent ;
- couvrir succès, échec, expiration et remboursement.

### 5. Travail d'impression

- créer `PrintJob` à partir d'une ligne payée ;
- ajouter sa machine à états ;
- permettre l'upload sécurisé d'un fichier ;
- afficher les jalons client ;
- tester les doubles notifications de paiement.

## Critères d'acceptation

- un prix ne peut pas être imposé depuis le navigateur ;
- la configuration achetée reste consultable après changement de tarif ;
- deux webhooks identiques ne créent qu'un travail ;
- un fichier privé n'est jamais accessible par URL publique permanente ;
- l'équipe voit le travail dans l'administration ;
- le client voit un statut simplifié dans son compte ;
- PHPUnit, PHPStan et ECS passent.

## Lots suivants

1. cartes de visite, dépliants, étiquettes et affiches ;
2. packs web et création de `WebProject` ;
3. projets photo/vidéo ;
4. abonnements SEO, maintenance et community management ;
5. intégrations fournisseurs et automatisations Yoowii Flow.
