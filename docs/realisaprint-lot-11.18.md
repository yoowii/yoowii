# Lot 11.18 — Sonde Redis Realisaprint

La commande suivante produit une ligne JSON, sans DSN ni contenu de configuration :

```bash
php bin/console yoowii:realisaprint:redis:check --env=prod --no-interaction
```

Elle vérifie `PING`, la limite `maxmemory`, le ratio mémoire, les évictions, les connexions rejetées et le nombre de clients connectés. Le code de sortie est différent de zéro si Redis est inaccessible, si aucune limite mémoire n’est configurée, si l’utilisation atteint 80 % ou si Redis a rejeté des connexions.

Cette commande peut être exécutée par le superviseur toutes les cinq minutes. Les compteurs d’évictions sont fournis pour suivre leur évolution ; une valeur non nulle isolée n’est pas une alerte à elle seule.
