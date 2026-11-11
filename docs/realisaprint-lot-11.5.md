# Lot 11.5 — Verrou Redis distribué de `show_variables`

Ce lot garantit qu'une configuration technique Realisaprint manquante ne déclenche qu'un seul appel `show_variables`, même lorsque les requêtes arrivent sur des serveurs Yoowii distincts.

## Fonctionnement

`RedisRealisaprintVariableStateLock` acquiert un verrou Redis atomique `SET NX` avant de calculer une entrée absente du cache. Le verrou utilise :

- une clé dérivée de la clé canonique `show_variables` ;
- un jeton aléatoire, vérifié dans un script Lua avant la libération ;
- une durée de vie de 180 secondes, pour couvrir l'attente éventuelle du throttle global avant l'appel fournisseur tout en évitant un verrou bloqué après l'arrêt d'un worker ;
- une attente maximale de 210 secondes.

Lorsqu'une seconde requête obtient le verrou après la première, elle relit d'abord `cache.app`. Elle récupère donc la réponse que le premier worker a déjà stockée, sans appeler Realisaprint une seconde fois.

Les réactualisations probabilistes sont désactivées pour ce cache : l'état fournisseur reste valable pendant son TTL de 30 jours. Cela évite qu'une expiration anticipée entraîne un appel inutile vers une API limitée à une requête toutes les 15 secondes.

## Pré-requis

Le même `YOOWII_REDIS_DSN` doit être fourni à toutes les instances de production, et l'extension PHP `redis` doit être activée. Redis est désormais une dépendance de disponibilité de la consultation `show_variables` en production, comme il l'est déjà pour le cache partagé du lot 11.4.

## Vérification

1. Vider `cache.app`.
2. Lancer deux requêtes HTTP identiques vers le refresh du configurateur, idéalement depuis deux workers ou deux instances.
3. Vérifier qu'un seul appel `show_variables` est observé côté Realisaprint ; la seconde réponse doit attendre puis être servie depuis Redis.

Le test unitaire vérifie que le verrou reçoit la même clé canonique que le cache. Le test de concurrence multi-processus doit être exécuté dans l'environnement Docker ou de préproduction disposant de Redis.
