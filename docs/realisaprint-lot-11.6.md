# Lot 11.6 — Throttle Redis global des appels Realisaprint

Realisaprint impose au minimum 15 secondes entre deux appels à un même endpoint. Le garde précédent rejetait la seconde requête avec une erreur de rate limit. Ce lot la place désormais en attente et la réserve dès que le créneau est disponible.

## Fonctionnement

`RedisRealisaprintRequestThrottle` utilise un script Lua Redis atomique :

- si aucun créneau n'est réservé, il crée une clé de 15 secondes et laisse l'appel partir ;
- sinon, il attend jusqu'à l'expiration de la clé puis retente la réservation ;
- la clé est spécifique à l'opération Realisaprint, comme l'était le garde précédent ;
- l'attente maximale est de 90 secondes, après quoi une erreur explicite `rate limit` est renvoyée pour que le mécanisme de fallback de devis reste actif.

Le throttle est partagé par tous les workers et toutes les instances grâce au même `YOOWII_REDIS_DSN`. Il complète le lot 11.5 : le verrou distribué évite les doublons d'une même configuration, tandis que le throttle sérialise les configurations différentes vers l'endpoint limité.

## Vérification

En préproduction, vider `cache.app`, puis envoyer deux requêtes `show_variables` différentes à moins de 15 secondes d'intervalle. La seconde ne doit pas échouer : elle doit attendre puis appeler le fournisseur après le premier créneau.
