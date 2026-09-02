# Lot 8 — Portail client PrintJob

## Objectif

Permettre au client connecté de gérer ses dossiers print, tout en gardant un accès invité signé et une séparation stricte entre échanges client et exploitation interne.

## Périmètre livré

- page « Mes impressions » : `/fr_FR/account/print-jobs` ;
- accès aux dossiers appartenant au client connecté ;
- maintien des liens invités signés et révocables pour un dossier individuel ;
- dépôt, aperçu et remplacement de fichier tant que le BAT n’est pas validé ;
- validation du BAT ;
- refus du BAT avec motif obligatoire ;
- retrait du BAT refusé et retour à `bat_pending` ;
- réouverture automatique de l’upload après refus ;
- motif client consultable par le client et les opérateurs ;
- notification Sylius de refus envoyée à l’équipe production ;
- timeline client et suivi transport conservés.

## Règles de sécurité

- les fichiers restent hors de `public/` et sont lus via un contrôleur autorisé ;
- chaque mutation POST vérifie le CSRF ;
- un client connecté ne voit que les dossiers liés à son `Customer` ;
- un invité ne peut accéder qu’à une URL signée, révocable et limitée dans le temps ;
- les notes internes `PrintJobNote` ne sont jamais affichées dans le storefront ;
- les motifs client sont stockés dans `PrintJobCustomerMessage`, séparément des notes opérateur.

## Recette

1. Connecter un client ayant une commande print payée et ouvrir `/fr_FR/account/print-jobs`.
2. Vérifier qu’il ne voit que ses dossiers et ouvrir un suivi.
3. Déposer un fichier puis faire publier un BAT par un opérateur.
4. Refuser le BAT avec un motif.
5. Vérifier : BAT retiré, statut `bat_pending`, motif visible, upload à nouveau possible.
6. Déposer le fichier corrigé, publier un nouveau BAT et l’approuver.
7. Vérifier le suivi après expédition et qu’un lien invité signé ne donne accès qu’au dossier concerné.
