# Lot 11.17 — Exploitation Redis en production

Redis est une dépendance partagée des caches Realisaprint, des verrous distribués et du throttle fournisseur. L’instance doit être privée, accessible depuis chaque processus PHP Yoowii et protégée par mot de passe/TLS selon le réseau retenu.

## Paramètres recommandés

Configurer une limite mémoire adaptée au VPS et une éviction LRU afin qu’une charge de cache n’empêche jamais Redis de démarrer ou le système de rester disponible :

```conf
maxmemory 512mb
maxmemory-policy allkeys-lru
```

`allkeys-lru` convient ici car les clés sont toutes reconstructibles : cache Symfony, états fournisseur, verrous à TTL court, throttle et compteur de popularité. Ne pas utiliser cette instance Redis pour une donnée métier non reconstructible sans revoir cette politique.

Après modification de la configuration Redis, vérifier :

```bash
redis-cli -u "$YOOWII_REDIS_DSN" INFO memory
redis-cli -u "$YOOWII_REDIS_DSN" CONFIG GET maxmemory maxmemory-policy
redis-cli -u "$YOOWII_REDIS_DSN" PING
```

Le DSN de production reste injecté hors dépôt :

```dotenv
YOOWII_REDIS_DSN=redis://:mot-de-passe@redis.interne:6379/0
```

L’extension PHP `redis` doit être active sur chaque runtime PHP-FPM/CLI qui exécute Yoowii.

## Supervision minimale

Déclencher une alerte quand l’un des seuils suivants est atteint pendant cinq minutes :

- `used_memory / maxmemory` supérieur à 80 % ;
- `evicted_keys` augmente continuellement hors montée en charge attendue ;
- `rejected_connections` est supérieur à zéro ;
- Redis ne répond plus au `PING` ;
- les logs Yoowii signalent une erreur Redis ou une attente Realisaprint anormalement longue.

En cas de saturation, augmenter d’abord la mémoire allouée ou réduire les TTL non critiques ; ne jamais purger Redis en aveugle en période de trafic. Une éviction peut provoquer davantage d’appels Realisaprint, mais les verrous et le throttle limitent cet effet.
