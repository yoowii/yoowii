# Yoowii préproduction — audit des traitements asynchrones (#4)

Statut : **audit de code uniquement**. Aucune tâche, aucun worker et aucune commande fournisseur ne doivent être activés implicitement par ce document.

## Configuration actuellement versionnée

- `config/packages/messenger.yaml` déclare le transport `async: '%env(MESSENGER_TRANSPORT_DSN)%'` et `sync: 'sync://'`. Le transport de défaillances `failed` et `failure_transport` sont commentés.
- Deux messages sont routés vers `async` :
  - `App\Yoowii\PrintProduction\Application\Message\PreflightPrintAsset` → `PreflightPrintAssetHandler` : inspection des médias client, rapport, activité métier et écriture en base.
  - `App\Yoowii\Sourcing\Application\Message\SynchronizeRealisaprintCatalogConfigurations` → `SynchronizeRealisaprintCatalogConfigurationsHandler` : chargement et traitement d'une synchronisation fournisseur.
- `compose.preprod.yml` déclare `php`, `nginx`, `mysql`, `redis` mais **aucun worker** et ne transmet pas explicitement `MESSENGER_TRANSPORT_DSN` au service PHP.
- `.env` versionné contient une configuration du transport, mais **il faut vérifier la valeur réellement résolue dans le conteneur** : ne pas supposer qu'elle est correctement présente en production. Ne pas afficher le DSN ni les secrets dans les journaux.

## Inventaire des commandes et risques

| Commande Symfony | Objet | Précaution avant planification |
| --- | --- | --- |
| `yoowii:print-preflight:dispatch-pending` | Réenvoie les rapports d'analyse d'illustrations clients en attente via Messenger | Peut redéclencher des analyses et modifier les rapports ; nécessite worker prêt et test d'idempotence |
| `yoowii:print-preflight:recheck-active` | Programme la réanalyse d'illustrations actives | Ne pas activer en boucle sans validation fonctionnelle |
| `yoowii:print-jobs:reconcile` | Crée des jobs d'impression manquants pour les commandes payées | Mutation métier : vérifier qu'aucune commande fournisseur n'est envoyée automatiquement |
| `yoowii:print-jobs:sync-realisaprint` | Consulte et met à jour les statuts des commandes fournisseur existantes | Trafic API fournisseur, nécessite limites et gestion d'échecs |
| `yoowii:print-jobs:send-notifications` | Expédie les notifications email de production en file | Risque d'envois clients : **désactivé en préprod** sans boîte/mail sink maîtrisé |
| `yoowii:print-jobs:alert-late` | Enfile puis envoie des alertes de retard | Risque email client : **désactivé par défaut** |
| `yoowii:realisaprint:catalog:sync` | Synchronise le catalogue fournisseur en lecture côté API | Écrit en base, à déclencher en supervision avec limites API |
| `yoowii:realisaprint:catalog-configurations:sync` | Crée et traite une synchronisation de configurations | Peut modifier la base ; dispose d'un verrou métier `activeLock` ; tester récupération de verrou bloqué |
| `yoowii:realisaprint:linked:sync` | Rafraîchit les configurations liées et vérifie le mapping | Risque de changement de données publiées : valider d'abord |
| `yoowii:realisaprint:display-state:warm` | Rafraîchit des états d'affichage fournisseur et persiste en base | Trafic fournisseur et écritures ; tester les erreurs sans affecter le storefront |

Les noms et effets sont issus des commandes présentes sur `chore/ci-cd`. **Aucune cadence de cron n'est actuellement validée.**

## Diagnostic VPS sans mutations métier

Ne pas afficher `.env`, les secrets, ni `MESSENGER_TRANSPORT_DSN` en clair.

```bash
cd /opt/yoowii/preprod

# Conteneurs effectifs et statut
docker compose --env-file .env -f compose.preprod.yml ps

# Vérifier seulement si le DSN Messenger est présent dans l'environnement
docker compose --env-file .env -f compose.preprod.yml exec -T php \
  sh -c 'if [ -n "${MESSENGER_TRANSPORT_DSN:-}" ]; then echo "Messenger DSN: présent"; else echo "Messenger DSN: absent du conteneur"; fi'

# Routage / handlers enregistrés ; lecture seule
docker compose --env-file .env -f compose.preprod.yml exec -T php \
  php bin/console debug:messenger --env=prod --no-debug

# Vérifier la configuration résolue sans imprimer les URLs/DSN
docker compose --env-file .env -f compose.preprod.yml exec -T php \
  php bin/console list messenger --raw --env=prod --no-debug
```

Éviter `messenger:consume`, `messenger:failed:retry` et toutes les commandes du tableau pendant ce diagnostic. Ne pas lancer `doctrine:migrations:migrate` automatiquement sur la base préprod.

## Mise en service proposée (lot d'implémentation distinct)

1. **Décision transport** : choisir un transport supporté par Symfony et le projet ; documenter la DSN sans exposer son secret. Ne pas présumer que Redis utilisé pour le cache peut servir de transport Messenger sans configuration et test.
2. **Échecs/retries** : configurer `failure_transport`, délais de retry bornés, journaux sans informations client et procédure de réexécution. Vérifier la durabilité du transport.
3. **Worker indépendant** : profil/service Docker `worker` ou unité systemd supervisée, avec `messenger:consume async --time-limit=3600 --memory-limit=256M --limit=100`, arrêt SIGTERM et redémarrage contrôlé. L'ajout d'un service Compose exige une **procédure de déploiement infrastructure** séparée : le garde-fou de l'issue #3 refuse tout changement de liste de services.
4. **Tests avant activation** : publier un message de test inoffensif sur environnement jetable ; vérifier traitement unique, rejeu, échec, verrou, worker arrêté et redémarrage ; mesurer la charge et confirmer qu'aucune commande réelle fournisseur n'est déclenchée.
5. **Cron séparés** : activer seulement après validation métier, avec `flock`, timeout, journalisation, supervision et fenêtre/cadence explicites. Ne jamais planifier les emails clients en préprod sans mail sink.

### Critères de passage

- Les messages déposés sont consommés et les échecs sont consultables et rejouables de manière contrôlée.
- Le worker est observable, supervisé, sans double consommation ni perte de messages.
- Les tâches planifiées sont documentées, non concurrentes et validées une par une.
- Aucun déclenchement de commande fournisseur ni envoi email client sans autorisation spécifique.
