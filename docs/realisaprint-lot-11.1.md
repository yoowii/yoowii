# Lot 11.1 — Cache serveur de `show_variables`

Ce lot supprime les appels fournisseur répétés pour une même configuration technique Realisaprint. Il ne modifie pas encore le debounce, le cache navigateur, la réponse stale ni l'auto-correction du formulaire : ces changements restent des lots séparés afin de préserver le comportement actuel du configurateur.

## Clé de cache canonique

Avant l'appel fournisseur, Yoowii convertit la configuration client en payload Realisaprint. Les valeurs fixes, les défauts techniques et les options masquées nécessaires au fournisseur sont donc déjà inclus. Les variables `VARTICLE_*` sont triées par clé avant la création de l'empreinte.

La clé contient :

- le produit et le stock Realisaprint ;
- les variables canoniques réellement envoyées ;
- la version du mapping ;
- la version de schéma du configurateur.

Une publication de mapping ou une modification de schéma rend ainsi les anciens états inatteignables sans nécessiter de purge globale.

## Comportement

`RealisaprintConfiguratorRefresh` passe désormais par `RealisaprintVariableStateCache` avant d'appeler `show_variables` :

- cache hit : l'état normalisé, sans identifiants fournisseur, est renvoyé immédiatement ;
- cache miss : `show_variables` est appelé une seule fois puis son état normalisé est stocké ;
- le TTL est fixé à 30 jours (2 592 000 secondes).

La limite de 15 secondes reste une protection finale dans `RealisaprintClient`. Le TTL ne peut pas être configuré sous 15 secondes.

## Pré-requis production

Le service utilise le pool Symfony `cache.app`. Avant d'activer l'API Realisaprint sur plusieurs processus PHP ou plusieurs serveurs, ce pool doit être configuré sur une instance Redis commune ; le cache filesystem par défaut ne permet pas de mutualiser les états ni de coalescer les requêtes entre instances.

La mise en place Redis (pool partagé, verrou distribué et stale-while-revalidate) est volontairement distinguée du présent lot : elle doit être validée avec l'infrastructure de déploiement avant d'être rendue obligatoire.

## Vérification

Exécuter au minimum :

```bash
make test
make phpstan
make cs
```

Le test `RealisaprintVariableStateCacheTest` vérifie que deux payloads techniquement identiques, même si l'ordre des variables diffère, réutilisent le même état, et que toute évolution du mapping ou du schéma produit une nouvelle clé.
