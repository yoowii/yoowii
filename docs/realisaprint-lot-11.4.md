# Lot 11.4 — Cache partagé Redis et coalescence des cache miss

Le cache de `show_variables` devient partagé en environnement `prod`. Tous les workers PHP et toutes les instances Yoowii utilisent le même Redis via `cache.app`.

## Effet sur la limite Realisaprint

`RealisaprintVariableStateCache` passe l'appel fournisseur dans le callback de `CacheInterface`. Symfony protège ce callback contre le *cache stampede* : sur une même instance, un seul worker calcule une valeur manquante. Redis rend ensuite cette valeur immédiatement visible à tous les workers et toutes les instances Yoowii.

La protection implicite de Symfony est locale à l'hôte ; deux instances différentes qui rencontrent exactement le même cache miss au même instant peuvent encore chacune faire un appel fournisseur. Le prochain lot ajoutera un verrou distribué Redis explicite (`symfony/lock`) pour coalescer également ce cas rare. La clé de cache reste déjà canonique : produit, stock, variables Realisaprint triées, version de mapping et version de schéma.

## Configuration de déploiement

Définir cette variable dans l'environnement de production, avec une instance Redis accessible par tous les processus Yoowii :

```dotenv
YOOWII_REDIS_DSN=redis://:mot-de-passe@redis.exemple.internal:6379/0
```

La variable ne doit pas être ajoutée au fichier `.env` versionné. En local, le fichier `compose.yml` fournit Redis et `compose.override.dist.yml` configure `redis://redis:6379`.

L'extension PHP `redis` doit être activée sur les serveurs exécutant l'application. Sans `YOOWII_REDIS_DSN`, la configuration `prod` échoue volontairement au démarrage : ce serait dangereux de croire le cache distribué alors que les instances utiliseraient des caches locaux distincts.

## Vérification

Après déploiement :

```bash
bin/console cache:pool:clear cache.app
bin/console cache:pool:list
```

Puis ouvrir deux configurateurs identiques et contrôler que le premier appel peuple Redis ; les requêtes suivantes pour la même configuration doivent être servies depuis Redis. Sur une seule instance, un cache miss concurrent est coalescé par Symfony.
