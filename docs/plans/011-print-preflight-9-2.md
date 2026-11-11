# Lot 9.2 — Préflight PDF avancé et correction client

## Objectif

Compléter le contrôle léger du lot 9.1 par un moteur prépresse PDF et donner à l’équipe production un parcours explicite pour demander un fichier corrigé au client.

## Contrôles avancés

- chiffrement du PDF ;
- `TrimBox` et `BleedBox` ;
- polices incorporées ;
- résolution minimale des images intégrées ;
- couverture CMJN via Ghostscript ;
- détection d’un standard PDF/X ou d’un profil de sortie ;
- métadonnées techniques enregistrées dans le rapport existant, associé au checksum du fichier.

Une police non incorporée, une image sous 150 dpi ou un PDF chiffré est bloquant. Les profils absents, un document monochrome, un fond perdu ambigu ou une image entre 150 et 299 dpi sont des avertissements à traiter par l’opérateur.

## Correction client

Depuis un rapport `warning` ou `failed` du fichier actif, l’opérateur peut envoyer une demande de correction. Elle :

1. stocke le texte dans `PrintJobCustomerMessage`, donc jamais dans une note interne ;
2. ajoute une ligne à l’historique ;
3. envoie un e-mail Sylius avec un lien vers Yoowii Flow ;
4. laisse le client déposer un fichier de remplacement ;
5. relance automatiquement le préflight sur le nouveau checksum.

## Prérequis d’exécution

Le conteneur qui exécute `messenger:consume async` doit contenir :

```text
poppler-utils ghostscript
```

Le worker doit être isolé, sans accès sortant inutile et avec les mêmes droits minimaux que le stockage privé des fichiers. L’absence de ces binaires produit une erreur bloquante sûre : le fichier ne peut pas partir en production sans analyse prépresse.

Après le déploiement du lot, les rapports déjà terminés par le lot 9.1 peuvent être enrichis une seule fois :

```bash
bin/console yoowii:print-preflight:recheck-active
```

## Limites assumées

Le lot détecte la présence d’un profil de sortie ou du marqueur PDF/X ; il ne certifie pas encore la conformité complète d’un PDF/X, les surimpressions, les tons directs ou la conformité ICC du profil. Ces contrôles nécessiteront un moteur spécialisé et les règles spécifiques de chaque fournisseur.
