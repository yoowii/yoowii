# Lot 6 — Production print et Yoowii Flow

## Objectif

Transformer chaque ligne print d'une commande payée en un unique `PrintJob`, sans transformer la commande Sylius en agrégat de production.

## Garanties

- contrainte unique SQL sur `order_item_id` ;
- création rejouable via l'événement de paiement et `yoowii:print-jobs:reconcile` ;
- snapshot de production immuable : commande, variante, configuration, fournisseur, matrice et coûts ;
- fichiers privés hors de `public/`, noms nettoyés, taille maximale 100 Mo, checksum SHA-256 ;
- statuts client : attente fichiers, fichiers reçus, BAT, production, expédition, livraison, blocage ou annulation ;
- approbation du BAT protégée par propriété du dossier et jeton CSRF ;
- aucune URL directe du fichier privé n'est exposée.

## Cycle MVP

`awaiting_files → files_received → bat_pending → bat_ready → bat_approved → in_production → shipped → delivered`

`blocked` et `cancelled` sont disponibles pour le traitement opérateur. `delivered` et `cancelled` sont terminaux.

## Exploitation

Après déploiement, exécuter une première réconciliation :

```bash
bin/console yoowii:print-jobs:reconcile
```

Le endpoint Flow `GET /{locale}/account/flow/print-jobs/{reference}` retourne un état client sûr. Le dépôt de fichier et l'approbation du BAT utilisent des routes POST authentifiées avec CSRF.

La soumission automatique au fournisseur reste hors MVP : l'opérateur renseigne la référence fournisseur, puis le suivi transport. Une future intégration devra passer par Messenger et conserver les mêmes invariants d'idempotence.
