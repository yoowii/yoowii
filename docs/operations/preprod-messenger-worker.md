# Issue #4 — Messenger worker en préproduction (activation progressive)

**État : prêt pour revue, NON activé.** Cette branche ajoute un overlay Compose distinct et une injection contrôlée du DSN dans le service PHP principal. **Fusionner cette PR peut redéployer PHP/Nginx automatiquement** avec le pipeline de l'issue #3 : ne pas fusionner avant validation des changements et du plan de bascule.

## Décisions techniques

- `config/packages/messenger.yaml` route **uniquement** `PreflightPrintAsset` vers `async`, la seule file consommée par le worker initial. `SynchronizeRealisaprintCatalogConfigurations` est routé vers un transport distinct `yoowii_supplier_async` (file Doctrine `yoowii_supplier_async`) **sans consommateur actif**. L'activation du fournisseur devra être traitée dans un lot séparé.
- Le DSN Messenger versionné utilise le **transport Doctrine**. Le Redis du Compose principal est un cache LRU volatile (`appendonly no`), non adapté à une file durable.
- Le service `worker` utilise la même image PHP et la même base de données que le storefront, et monte le volume privé des fichiers d'impression. Il est défini explicitement (sans héritage du réseau `web`), n'expose aucun port et reste sur le réseau `internal`. Les variables liées aux emails, à l'intégration fournisseur Realisaprint, au FTP et à la publication rapide sont désactivées dans cet overlay. Cette désactivation ne constitue pas une validation des handlers ; il faut confirmer leur comportement en environnement isolé avant activation.
- Le worker est défini uniquement dans `compose.preprod.worker.yml`, avec le profil explicite `messenger`. **Le pipeline automatique ne charge jamais cet overlay** ; il n'est pas démarré par une fusion.
- Le service PHP principal reçoit `MESSENGER_TRANSPORT_DSN: ${YOOWII_MESSENGER_TRANSPORT_DSN:-doctrine://default}` : **sans opt-in VPS, il conserve la file historique `default`**. Le service worker reçoit la variable dédiée **obligatoire** `YOOWII_MESSENGER_TRANSPORT_DSN` depuis le `.env` du VPS ; tous deux doivent utiliser exactement `doctrine://default?queue_name=yoowii_async` avant le lancement du worker. Il n'y a ni déplacement ni suppression des 19 messages `default`.
- Le conteneur PHP reçoit `YOOWII_SUPPLIER_MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=yoowii_supplier_async` par défaut, ce qui évite toute nouvelle synchronisation Realisaprint dans `default`. Le worker de cette PR n'écoute que `async` et ne peut donc pas vider cette file fournisseur.
- Les transporteurs Sylius existants (`SYLIUS_MESSENGER_TRANSPORT_*`) restent séparés ; ne pas les retoucher ni consommer la file `default`.
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
- [ ] Une fois la PR fusionnée et PHP redéployé, ajouter `YOOWII_MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=yoowii_async` au `.env` VPS (mode 600, propriété root), puis effectuer un **redéploiement PHP/Nginx contrôlé** : la configuration des conteneurs est capturée à leur création. Ne pas toucher aux secrets préexistants.
- [ ] Vérifier dans PHP que `MESSENGER_TRANSPORT_DSN` pointe vers `yoowii_async` sans afficher le DSN ; faire de même pour le worker avec `docker compose config` en évitant l'affichage complet de la configuration qui contiendrait des secrets.
- [ ] Évaluer les messages émis entre la fusion et la bascule effective du PHP : ils peuvent encore aller dans `default`. Éviter de produire des messages Yoowii pendant cette fenêtre.
- [x] Inventaire catégoriel de la file historique `default` : **18 messages Sylius/promotions catalogue, 1 message Yoowii/PreflightPrintAsset** (19–27/10/2026), classifiés par recherche de chaînes sans lecture des données. Ne pas les déplacer, traiter ou supprimer sans analyse métier.
- [ ] Examiner la quantité et les types de messages déjà en attente, sans divulguer les données personnelles. Si des tâches Realisaprint sont en file, obtenir l'accord métier avant toute consommation.
- [ ] Vérifier que les services/handlers accèdent aux mêmes données et fichiers privés depuis le worker ; réaliser un essai sur une base isolée avec un message sans effet fournisseur.
- [x] Définir les retries applicatifs Yoowii : 3 réessais avec délais de 1, 2 et 4 secondes (plafond 60 secondes) ; transport d'échec dédié `yoowii_failed` (Doctrine). Aucune configuration globale `failure_transport` : ne pas changer les stratégies Sylius.
- [ ] Valider en test isolé l'échec définitif, le routage vers `yoowii_failed`, le rejeu volontaire et le comportement d'un message poison ; ne jamais faire ce test sur la file historique `default`.
- [ ] Définir le seuil d'alerte du backlog et son relevé sans exposer le contenu des messages.
- [x] Ajouter un contrôle opérationnel en lecture seule et des tests Docker simulés (version PHP/worker, backlogs, échecs, restart).
- [ ] Valider sur VPS le budget mémoire/CPU, la supervision, l'arrêt SIGTERM et la non-concurrence ; démarrer à **une seule instance**.
- [x] Transport fournisseur isolé : seul le préflight est routé vers le transport consommé par le worker. Test isolé vérifiant que les messages de synchronisation sont déposés dans `yoowii_ci_supplier_async` et non dans la file du worker (résultat CI à confirmer).
- [ ] Vérifier l'absence d'email client ou d'ordre fournisseur non désiré ; la séparation des files limite le risque mais ne remplace pas un audit des handlers.

## Supervision et gestion des échecs (lecture seule)

Sur préproduction, une fois la nouvelle image et la variable producteur/worker cohérentes, relever les compteurs **sans lire les corps ni les en-têtes des messages** :

```bash
docker compose --env-file .env -f compose.preprod.yml exec -T php \
  php bin/console doctrine:query:sql \
  "SELECT queue_name, COUNT(*) AS total, SUM(delivered_at IS NOT NULL) AS reserves FROM messenger_messages GROUP BY queue_name" \
  --env=prod --no-debug
```

Ne pas utiliser `messenger:failed:retry`, `messenger:failed:remove` ou `messenger:consume` tant que les types, l'idempotence et les effets métier des messages ne sont pas approuvés. Le transport `yoowii_failed` est distinct de la file `default` et des transports d'échec Sylius. Le transport fournisseur `yoowii_supplier_async` et son échec `yoowii_supplier_failed` restent également distincts, sans consommateur prévu dans cette phase.

## Supervision du worker et cycle de vie des versions

Le script `scripts/preprod/check-messenger-worker.sh` effectue exclusivement des **lectures** : identité de l'image PHP, présence et version du worker, compteur de redémarrages, nombres de messages de `yoowii_async`, `yoowii_failed` et `yoowii_supplier_async`, ancienneté des messages prêts. Il ne lit pas les corps, n'affiche pas les DSN et ne démarre aucun conteneur. Il doit être installé depuis un **commit revu** dans `/opt/yoowii/preprod/scripts/preprod/` ; le pipeline actuel ne synchronise pas ce fichier sur le VPS.

```bash
# Contrôle informatif : le worker peut être arrêté intentionnellement.
bash /opt/yoowii/preprod/scripts/preprod/check-messenger-worker.sh

# À utiliser dans une surveillance seulement APRÈS activation volontaire.
bash /opt/yoowii/preprod/scripts/preprod/check-messenger-worker.sh --require-running
```

Codes de sortie : 0 = nominal ; 1 = alerte (file échouée non vide, plus de 50 messages prêts, message prêt depuis plus de 15 minutes, worker manquant en mode strict, redémarrage ou image différente) ; 2 = incident (service PHP incorrect, base indisponible, réponse SQL invalide). La file fournisseur est comptabilisée mais n'est jamais consommée dans cette phase. Le seuil de backlog reste une valeur initiale à ajuster après observation.

**Avant tout déploiement de `chore/ci-cd` une fois le worker activé**, arrêter proprement le worker et noter l'image déployée (sans afficher de variables secrètes). Le pipeline automatique ne met à jour que PHP et Nginx : il **ne remplace jamais** le conteneur worker ni son overlay.

```bash
cd /opt/yoowii/preprod

# Arrêter le seul worker (aucun down / aucun prune)
docker compose --env-file .env -f compose.preprod.yml \
  -f compose.preprod.worker.yml --profile messenger stop -t 90 worker

# Effectuer ensuite le déploiement applicatif standard via GitHub Actions.
# Ne PAS redémarrer automatiquement le worker pendant la promotion.
```

Après réussite du déploiement applicatif : vérifier que la version PHP attendue est présente, installer **manuellement** l'overlay du même commit approuvé (fichier root-owned), valider le modèle Compose, puis refaire les contrôles de DSN, de backlog et de sécurité avant toute recréation du worker. Seule une activation explicitement approuvée autorise `up -d --no-deps --force-recreate worker`. Ne pas redémarrer un worker sur une image obsolète. En cas de rollback de l'application, maintenir le worker à l'arrêt jusqu'à vérification de l'image et des schémas correspondants.

**Limitation volontaire :** cette procédure dépend d'une intervention manuelle avant chaque nouveau déploiement ; pour automatiser le renouvellement du worker, une évolution du script de déploiement root-owned et de ses garde-fous nécessitera une PR séparée avec rollback et contrôle d'une file non consommée.

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

- La file Doctrine historique a été inspectée **en lecture seule** : 19 messages dans `default` (18 promotions Sylius, 1 preflight Yoowii). Aucun message n'a été consommé ; l'activation n'est pas approuvée.
- Les emails et les tâches `cron` ne sont **pas inclus** dans cette PR.
- Le test de consommation réelle a été effectué **en CI isolée**, avec message fictif en erreur et file d'échec dédiée ; pas de consommation du VPS. Aucun déploiement VPS ni test d'un vrai fichier client n'a eu lieu.
- Ne pas considérer cette PR comme la clôture de l'issue #4 : la durabilité, le rejeu des échecs et la supervision restent à prouver.
