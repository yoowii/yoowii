# Plan 002 — Sourcing print multi-fournisseurs

## Objectif

Permettre à Yoowii de vendre un produit d'impression canonique tout en choisissant en interne l'imprimeur chargé de le produire et de l'expédier.

## Lot livré

- référentiel `PrintSupplier` avec mode d'intégration et capacités ;
- références techniques `SupplierProduct` ;
- mappings de configuration versionnés et datés ;
- matrices de coût versionnées, contrôlées par checksum et activables ;
- routes fixes par produit Yoowii, avec priorité principal/secours ;
- sélection déterministe refusant les priorités ambiguës ;
- mapping Doctrine, migration MySQL et tests unitaires.

## Décisions

- Le catalogue Sylius ne contient aucun code fournisseur.
- Le premier lot ne contacte aucune API externe.
- Une priorité faible est préférée : `10` est sélectionnée avant `20`.
- Les mappings et matrices sont ajoutés par version et non écrasés.
- Une grille archivée est définitive.
- La désactivation d'un fournisseur neutralise toutes ses routes.
- Les coûts fournisseurs ne sont pas stockés dans le `PricingSnapshot` client.

## Prochains incréments

1. ~~Repositories Doctrine pour le routage et les matrices.~~
2. ~~Import CSV contrôlé des matrices avec rapport d'erreurs.~~
3. ~~Calculateur flyer serveur utilisant une matrice active et un fournisseur de secours.~~
4. Écran d'administration des fournisseurs, routes et imports.
5. Endpoint de devis temporaire et intégration au configurateur storefront.
6. Création du `PrintJob` et de son `SourcingSnapshot` après paiement.
7. Premier adaptateur API, après obtention d'un environnement sandbox fournisseur.

## Critères d'acceptation

- Une route inactive, expirée ou rattachée à un fournisseur désactivé n'est jamais choisie.
- Une égalité de priorité bloque la sélection.
- Une commande historique peut référencer explicitement les versions de mapping et de matrice utilisées.
- Un nouvel import ne modifie pas les anciennes versions.
- La base valide le caractère unique des codes et versions métier.
