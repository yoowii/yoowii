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

La page Flow `GET /{locale}/flow/print-jobs/{reference}` présente un état client sûr. Le JSON de suivi reste disponible sous `/{reference}/status`. Le dépôt de fichier et l'approbation du BAT utilisent des routes POST protégées par propriété ou URL signée, ainsi que par CSRF.

La soumission automatique au fournisseur reste hors MVP : l'opérateur renseigne la référence fournisseur, puis le suivi transport. Une future intégration devra passer par Messenger et conserver les mêmes invariants d'idempotence.

## Lot 6.1 — Dépôt storefront et BAT client

Le client peut maintenant déposer ses fichiers depuis la page de remerciement et le détail de sa commande. Le parcours est accessible sans compte à partir du jeton de commande Sylius, puis chaque action utilise une URL signée valable 30 jours. Une incrémentation de `PrintJob::accessVersion` révoque immédiatement les anciens liens.

- glisser-déposer ou sélection de PDF, JPEG, PNG et TIFF jusqu'à 100 Mo ;
- progression de l'envoi avec repli HTML sans JavaScript ;
- confirmation explicite des contrôles du document ;
- remplacement autorisé jusqu'à la mise à disposition du BAT ;
- anciennes versions conservées en base pour l'audit et jamais exposées ;
- aperçu privé du fichier actif et du BAT, sans chemin de stockage public ;
- approbation définitive du BAT avec confirmation et CSRF ;
- affichage du statut de production et du suivi transport.

Le serveur doit autoriser la taille annoncée (`client_max_body_size` côté proxy et `upload_max_filesize` / `post_max_size` côté PHP). Les fichiers restent sous `var/private/print` et doivent être inclus dans la stratégie de sauvegarde, pas dans les assets publics.
