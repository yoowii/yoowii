# Issue #4 — Messenger worker en préproduction (activation progressive)

**État : prêt pour revue, NON activé.** Cette branche ajoute un overlay Compose distinct, sans modifier `compose.preprod.yml` ni le script de déploiement automatique de l'issue #3.

## Décisions techniques

- `config/packages/messenger.yaml` route `PreflightPrintAsset` et `SynchronizeRealisaprintCatalogConfigurations` vers le transport `async`.
- Le DSN Messenger versionné utilise le **transport Doctrine**. Le Redis du Compose principal est un cache LRU volatile (`appendonly no`), non adapté à une file durable.
- Le service `worker` utilise la même image PHP et la même base de données que le storefront, et monte le volume privé des fichiers d'impression. Il est défini explicitement (sans héritage du réseau `web`), n'expose aucun port et reste sur le réseau `internal`. Les variables liées aux emails, FTP et quick publication sont désactivées dans cet overlay.
- Le worker est défini uniquement dans `compose.preprod.worker.yml`, avec le profil explicite `messenger`. **Le pipeline automatique ne charge jamais cet overlay** ; il n'est pas démarré par une fusion.
- `MESSENGER_TRANSPORT_DSN` doit être explicitement configuré sur le VPS, dans le `.env` protégé, et est injecté dans le worker sans être affiché. Les transporteurs Sylius éventuels restent ceux résolus par l'application.
- Un worker peut consommer des messages préexistants, dont certains réalisent des opérations métier chez Realisaprint. **Ne pas démarrer avant revue des messages en file et accord explicite.**

## Étape A — Diagnostic sans effet métier sur le VPS

Aucune de ces commandes ne démarre un worker, n'envoie un email ou n'exécute une synchronisation fournisseur.

```bash
cd /opt/yoowii/preprod

# Vérifier si une variable est définie sans afficher sa valeur
if grep -Eq '^MESSENGER_TRANSPORT_DSN=.+$' .env; then
  echo 'MESSENGER_TRANSPORT_DSN: défini dans le .env VPS'
else
  echo 'MESSENGER_TRANSPORT_DSN: absent ou vide dans le .env VPS'
fi

# Vérifier les services et le tag applicatif actuel
docker compose --env-file .env -f compose.preprod.yml ps
grep '^YOOWII_IMAGE_TAG=' .env

# Vérifier le routage Messenger sans traiter de messages
docker compose --env-file .env -f compose.preprod.yml exec -T php \
  php bin/console debug:messenger --env=prod --no-debug

# État du stockage Doctrine (lecture seule, sans afficher les messages)
docker compose --env-file .env -f compose.preprod.yml exec -T php \
  php bin/console doctrine:query:sql \
  "SELECT COUNT(*) AS table_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'messenger_messages'" \
  --env=prod --no-debug
```

**Important :** ne pas afficher `.env`, la DSN, le contenu des messages ou les identifiants fournisseur dans les logs/PR. Si la table manque, ne pas lancer de création/migration dans cette phase.

## Étape B — Validation de l'overlay (après revue/merge)

Vérifier uniquement le modèle Compose, sans démarrer de conteneur :

```bash
# Copier compose.preprod.worker.yml depuis un commit approuvé, dans
# /opt/yoowii/preprod, en conservant la propriété root.
docker compose --env-file .env -f compose.preprod.yml \
  -f compose.preprod.worker.yml --profile messenger config --quiet
```

Si cette commande échoue, arrêter et corriger les prérequis sans utiliser `up`.

## Étape C — Conditions obligatoires avant activation

- [ ] Confirmer que la DSN Doctrine pointe vers la **bonne base**, que la table `messenger_messages` existe, et que le transport est **durable**.
- [ ] Examiner la quantité et les types de messages déjà en attente, sans divulguer les données personnelles. Si des tâches Realisaprint sont en file, obtenir l'accord métier avant toute consommation.
- [ ] Vérifier que les services/handlers accèdent aux mêmes données et fichiers privés depuis le worker ; réaliser un essai sur une base isolée avec un message sans effet fournisseur.
- [ ] Définir les retries, le transport `failed`, la politique de poison messages et les alertes de backlog.
- [ ] Valider le budget mémoire/CPU, la supervision, l'arrêt SIGTERM et la non-concurrence ; démarrer à **une seule instance**.
- [ ] Vérifier l'absence d'email client ou d'ordre fournisseur non désiré.

## Étape D — Activation manuelle uniquement (après autorisation)

Le nom `worker` ne doit pas être ajouté au Compose principal tant que le garde-fou de déploiement de l'issue #3 n'a pas de procédure infrastructure dédiée.

```bash
docker compose --env-file .env \
  -f compose.preprod.yml -f compose.preprod.worker.yml \
  --profile messenger up -d --no-deps worker

docker compose --env-file .env \
  -f compose.preprod.yml -f compose.preprod.worker.yml \
  --profile messenger ps worker
```

Le worker effectue `messenger:consume async` avec `--time-limit=3600`, `--memory-limit=256M` et `--limit=100` ; Compose le redémarre selon la politique héritée de `php`. Le service utilise le réseau interne, sans port publié. Contrôler les journaux en local sans diffuser le contenu sensible.

Pour arrêter ce worker volontairement sans toucher à PHP/MySQL/Nginx/Redis :

```bash
docker compose --env-file .env \
  -f compose.preprod.yml -f compose.preprod.worker.yml \
  --profile messenger stop worker
```

Ne pas lancer `down`, `down -v`, `prune` ou `messenger:consume` dans le conteneur PHP storefront.

## Limites

- La file Doctrine n'a pas encore été inspectée sur le VPS : l'activation n'est pas approuvée.
- Les emails et les tâches `cron` ne sont **pas inclus** dans cette PR.
- Aucun déploiement ni test de consommation réelle n'a été fait.
- Ne pas considérer cette PR comme la clôture de l'issue #4 : la durabilité, le rejeu des échecs et la supervision restent à prouver.
