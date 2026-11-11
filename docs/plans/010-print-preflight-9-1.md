# Lot 9.1 — Préflight technique des fichiers client

## Objectif

Contrôler le fichier exact envoyé par le client avant la préparation du BAT et avant tout passage en production. Chaque rapport est associé au checksum SHA-256 de son `PrintAsset`, jamais seulement au `PrintJob`.

## Fonctionnement

1. Un dépôt ou remplacement de fichier client crée un rapport `pending` et publie un message Symfony Messenger.
2. Le worker lit le fichier privé, enregistre le rapport puis écrit un évènement dans l’historique du dossier.
3. Le client et l’opérateur consultent le même résultat technique ; les notes internes restent séparées.
4. Un rapport `pending` ou `failed` empêche la publication d’un BAT et l’enregistrement de la commande fournisseur.
5. L’opérateur peut relancer l’analyse depuis le dossier de production.

## Contrôles fournis

- signature PDF, pages et dimensions de page ;
- formats commandés A3, A4, A5, A6, DL et dimensions explicites telles que `85x55` ;
- présence attendue du fond perdu de 3 mm ;
- dimensions pixels et DPI des JPEG, PNG et TIFF lorsque cette métadonnée est disponible ;
- erreur bloquante pour un format incohérent, une signature PDF invalide ou une résolution image sous 150 dpi ;
- avertissement pour les informations insuffisantes, le fond perdu absent ou une résolution de 150 à 299 dpi.

PDF/X, polices incorporées, surimpression, profils ICC et analyse fiable des TIFF seront traités dans le lot 9.2 avec un moteur PDF dédié : le rapport 9.1 ne prétend pas les valider.

## Exploitation

Le transport `async` réutilise `MESSENGER_TRANSPORT_DSN`, déjà présent dans le projet. En production, lancer un worker :

```bash
bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
```

Après une interruption de worker ou un déploiement, republier les contrôles en attente :

```bash
bin/console yoowii:print-preflight:dispatch-pending
```
