# Synchronisation des configurations catalogue RealiSAPrint

Depuis le catalogue sourcing, « Synchroniser les configurations de produit » crée un lot asynchrone. Le worker traite les produits un par un, conserve la dernière réponse valide et attend au moins 15 secondes après chaque réponse API avant l’appel suivant. Le rapport est disponible sur la page catalogue.

Le worker doit être actif en production :

```bash
bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
```

La commande peut être utilisée manuellement ou via un cron. Elle rafraîchit les configurations absentes ou erronées par défaut :

```bash
php bin/console yoowii:realisaprint:catalog-configurations:sync --env=prod --no-interaction
```

Pour forcer le rafraîchissement de toutes les configurations :

```bash
php bin/console yoowii:realisaprint:catalog-configurations:sync --mode=all --env=prod --no-interaction
```

Exemple de cron manuel, à activer seulement si une synchronisation récurrente est souhaitée :

```cron
0 2 * * 0 cd /var/www/vhosts/example/httpdocs && php bin/console yoowii:realisaprint:catalog-configurations:sync --env=prod --no-interaction
```
