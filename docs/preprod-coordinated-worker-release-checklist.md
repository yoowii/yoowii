# Préproduction Yoowii — contrôle avant la recette du déploiement coordonné

> Issue #5 / PR #14. **Lecture seule, aucune commande ci-dessous ne démarre un conteneur ni ne modifie le VPS.** Ne pas remplacer le script root-owned installé tant que cette revue et les tests CI ne sont pas approuvés.

## 1. Prérequis

- Vérifier que la PR #14 est revue, que la CI générale et le test isolé sont verts, et que les sauvegardes/restaurations sont documentées.
- Identifier le script réellement installé à `/usr/local/sbin/yoowii-deploy-preprod` et vérifier son propriétaire et ses permissions. Ne pas le remplacer pendant ce diagnostic.
- Confirmer l'état initial des services avant de prévoir une recette supervisée. Le worker est **opt-in** et ne doit pas être démarré par défaut.
- La préproduction partage un hôte Plesk avec d'autres sites : ne pas exécuter de commandes Docker globales destructrices ou `docker compose down`.

## Diagnostic automatisé, sans modification du VPS

Le script versionné [`scripts/preprod/diagnose-readonly.sh`](../scripts/preprod/diagnose-readonly.sh) réunit les contrôles purement consultatifs : fichiers et permissions, existence du tag immuable, présence **sans révéler la valeur** du transport Messenger dédié, nombre de workers, état et image Docker. Il n'exécute ni `docker compose up/down`, ni `docker exec`, ni `docker compose config` (qui pourrait exposer des secrets).

Sur une **copie locale du dépôt à la version revue** (et non par exécution directe d'une URL non vérifiée), vérifier puis exécuter :

```bash
bash -n scripts/preprod/diagnose-readonly.sh
sudo bash scripts/preprod/diagnose-readonly.sh
```

Codes de retour : `0` = aucun blocage ni avertissement remonté par ce script ; `1` = avertissements ; `2` = défaut détecté. **Le code 0 n'autorise pas à lui seul le déploiement** : la valeur réelle de la DSN, le contenu de l'overlay, les sauvegardes et l'idempotence restent à contrôler séparément. Le script n'affiche pas les valeurs de `.env`, les variables des conteneurs, ni la configuration Compose résolue.

Ne partager que les lignes `PASS/WARN/FAIL/INFO/RESULT` après vérification de l'absence de données sensibles. Ne pas lancer le script via `curl | bash` et ne pas communiquer le fichier `.env`.

## 2. Diagnostic sans secrets

Exécuter les commandes suivantes sur le VPS dans un terminal administrateur autorisé. Elles affichent des métadonnées de déploiement, pas le contenu de `.env` :

```bash
sudo stat -c '%U:%G %a %n' \
  /usr/local/sbin/yoowii-deploy-preprod \
  /opt/yoowii/preprod/compose.preprod.yml \
  /opt/yoowii/preprod/.env
sudo test ! -L /opt/yoowii/preprod/compose.preprod.worker.yml \
  && sudo stat -c '%U:%G %a %n' /opt/yoowii/preprod/compose.preprod.worker.yml
docker ps -a --filter label=com.docker.compose.project=yoowii-preprod \
  --format '{{.ID}} {{.Names}} {{.Image}} {{.Status}}'
docker ps -a --filter label=com.docker.compose.project=yoowii-preprod \
  --filter label=com.docker.compose.service=worker \
  --format '{{.ID}} {{.Image}} {{.Status}}'
```

Si le worker existe, vérifier **son image et son état sans afficher ses variables** :

```bash
docker ps -a --filter label=com.docker.compose.project=yoowii-preprod \
  --filter label=com.docker.compose.service=worker --format '{{.ID}}' |
  while IFS= read -r id; do
    docker inspect --format '{{.Name}} {{.Config.Image}} {{.State.Status}}' "$id"
  done
```

Ne pas copier dans un ticket les sorties de `docker inspect` sans `--format` ni de `docker compose config` : elles peuvent contenir des secrets.

## 3. Vérifications préalables

1. **Unicité** : au plus un worker portant les deux labels attendus ; aucun worker concurrent sur une autre instance n'est supposé sûr sans audit.
2. **Image** : s'il existe, le worker doit déjà référencer le même tag immuable que le PHP courant ; en cas de désaccord, arrêter la recette et investiguer manuellement.
3. **Overlay** : `compose.preprod.worker.yml` doit être un fichier ordinaire appartenant à root et non modifiable par groupe/autres. Vérifier son contenu hors des logs publics.
4. **Transport** : la variable `YOOWII_MESSENGER_TRANSPORT_DSN` doit désigner la queue Doctrine dédiée `yoowii_async`. Ne jamais consommer la queue héritée `default` ni la queue fournisseur.
5. **Isolation** : aucun port publié par le worker, réseau interne seulement, mail et intégrations Realisaprint désactivés.
6. **Messages en cours** : confirmer l'idempotence des handlers et l'arrêt gracieux (90 s) avant tout essai réel ; un message non terminé ne doit pas provoquer d'opération fournisseur involontaire.
7. **Retour arrière** : conserver le tag précédent et disposer d'une fenêtre de maintenance, d'une vérification HTTP et d'une procédure manuelle si la restauration automatique échoue.

## 4. Matrice de recette à valider en fenêtre supervisée

| Cas | Résultat attendu |
| --- | --- |
| Worker absent | Déploiement php/nginx normal, aucun worker activé |
| Worker arrêté | Même image que PHP après déploiement, reste arrêté |
| Worker actif sans message | Arrêt propre puis reprise sur la nouvelle image |
| Message inoffensif en cours | Fin contrôlée avant remplacement, sans doublon |
| Échec du pull avant swap | Ancien Compose intact, worker rétabli dans son état initial |
| Échec création/démarrage worker | Rollback des images et Compose ; état initial restauré |
| Rollback incomplet | Échec visible et incident à traiter, jamais annoncé comme réussi |

**Critère Go/No-Go :** tant que l'une de ces vérifications n'est pas validée, conserver la PR en brouillon et ne pas installer le nouveau script. Le succès de la CI simulée ne prouve pas la sécurité d'un redéploiement réel.

## 5. Points encore à auditer

- Image réelle et état du worker sur le VPS.
- Rétention et possibilité de restauration des sauvegardes (issue #5).
- Durée effective des handlers Messenger (issue #4), stratégie de retry et idempotence.
- Validation métier préalable à toute activation d'une file fournisseur.

Aucune commande fournisseur et aucune migration de base de données ne font partie de cette recette.

## 6. Avancement recette du 09/11/2026 — PR #14

### Vérifications réalisées

- Le contrôle SSH manuel [GitHub Actions #42641491907](https://github.com/yoowii/yoowii/actions/runs/42641491907) a réussi : un worker actif, image PHP alignée, transport Docker effectif Doctrine `yoowii_async`, commande `messenger:consume async`, désactivation des emails et des intégrations fournisseur.
- Le test isolé `test-rollback-isolated.py` vérifie désormais explicitement le `docker stop --time 90` et son ordre **avant** la recréation PHP/Nginx et du worker ; une réponse à l'arrêt laissant le conteneur actif bloque toute promotion.
- Ces vérifications sont **statiques ou simulées**. Elles ne démontrent ni l'arrêt réel d'un message en cours ni son éventuelle réexécution, ni la durabilité/idempotence des handlers.

### Critères de validation restants (No-Go jusqu'à recette supervisée)

1. **Sauvegarde et restauration vérifiées** de la configuration du déploiement et des données de préproduction ; documenter la procédure de retour arrière avant toute substitution du script root-owned.
2. **Message inoffensif en vol** : tester dans un environnement isolé avec le même runtime et la même configuration Messenger. Vérifier la prise en compte du SIGTERM, la durée réelle de handler, les délais retry, l'ACK et l'absence de doublon après redémarrage. Ne pas envoyer de message fournisseur/commande réelle.
3. **Simulation de panne et rollback surveillé** : tester restauration PHP/Nginx/worker et préservation des volumes et de l'état initial du worker. Aucune exécution automatisée sur le VPS partagé.
4. **Équivalence des contrôles de santé** : le nouveau script de déploiement conserve son contrôle strict HTTP 200 ; le diagnostic indépendant a relevé HTTP 302. À la demande de l'administrateur, **ne pas corriger ni modifier le contrôle HTTP dans ce chantier**, mais considérer cette incompatibilité comme un **blocage de promotion du nouveau deployer** tant que le comportement attendu n'est pas validé lors d'une fenêtre de recette.
5. **Revue de sécurité des logs de rollback** : la sortie brute `docker compose logs` vers GitHub Actions a été **supprimée** du nouveau script (PR #14), avec test isolé vérifiant qu'aucune commande de lecture des logs applicatifs n'est invoquée lors du rollback. Les diagnostics détaillés restent à recueillir localement sous contrôle d'accès si un incident se produit.

**Décision actuelle : No-Go sur installation du script de déploiement coordonné.** Les validations Docker/Messenger précédentes ne constituent pas une autorisation de mutation des conteneurs Plesk.


## 7. Validation fonctionnelle Messenger sur base isolée (09/11/2026)

La suite `tests/Yoowii/Messenger/MessengerRetryIntegrationTest.php` inclut
`testSafeProbeIsConsumedWithoutTouchingOtherQueues`. Elle utilise **uniquement**
`APP_ENV=test`, la base `sylius_test` du runner CI, le transport Doctrine
`yoowii_ci_async`, un message `SafeProbeMessage` et un handler `SafeProbeHandler`
sans effet métier. Le test vérifie :

1. l'envoi dans la file CI dédiée ;
2. la consommation et l'acquittement par `messenger:consume async --env=test --limit=1` ;
3. l'absence de message dans `yoowii_ci_failed` ;
4. la conservation exacte des compteurs des files `default` et
   `yoowii_ci_supplier_async`.

Le message et son handler ne sont routés/enregistrés que par
`config/packages/test/messenger.yaml` et `config/services_test.yaml`.
**Ne pas les copier, dispatcher ou activer sur le VPS.** Le résultat du test
ne prouve pas que `PreflightPrintAssetHandler` est idempotent lors d'un arrêt
en cours de traitement et d'un redémarrage. Cette preuve reste à réaliser
dans une instance isolée, sans fournisseurs.

## 8. Sauvegardes et restauration avant toute recette réelle

**Procédure à préparer, non exécutée sur le VPS.** La sauvegarde doit être
externe au répertoire remplacé par le déploiement et protégée contre les
accès non autorisés. Le déployeur proposé ne modifie pas la base ou les
volumes volontairement, mais une sauvegarde/restauration vérifiable reste
obligatoire avant de remplacer un composant actif sur un VPS Plesk partagé.

### Inventaire lecture seule

```bash
sudo stat -c '%U:%G %a %n' /usr/local/sbin/yoowii-deploy-preprod \
  /opt/yoowii/preprod/.env /opt/yoowii/preprod/compose.preprod.yml \
  /opt/yoowii/preprod/compose.preprod.worker.yml
docker ps -a --filter label=com.docker.compose.project=yoowii-preprod \
  --format '{{.Names}} {{.Image}} {{.Status}}'
```

Ne jamais afficher le contenu du `.env`, `docker inspect` complet,
`docker compose config` résolu ou les messages dans les tickets.

### Plan de sauvegarde supervisé

1. Identifier la dernière sauvegarde Plesk du site **Yoowii uniquement**,
   la base MySQL dédiée, et les volumes persistants concernés ; contrôler
   sa date, l'outil et la rétention, sans partager les secrets.
2. Conserver une copie root-only (`0700` pour le répertoire, `0600` pour
   chaque fichier contenant des secrets) du déployeur installé, des Compose,
   du `.env` et du tag d'image actuellement actif. Ne pas conserver cette
   copie dans GitHub ou dans les logs Actions.
3. Effectuer **un test de restauration vers une instance isolée**, pas vers
   les volumes actifs. Vérifier que la base se restaure, que les montages
   attendus existent et que les secrets ne sont pas consignés.
4. Documenter le point de décision Go/No-Go, la personne habilitée à
   arrêter la recette et le chemin de reprise si le rollback automatique
   échoue. Ne jamais lancer `docker compose down -v`, de prune global, ou
   une restauration MySQL sur la base active pour un simple test.

### Procédure de retour arrière (à valider en fenêtre supervisée)

- Si l'installation du **nouveau script de déploiement** devait échouer avant
  son premier usage, restaurer uniquement l'ancien exécutable root-owned
  à partir de la copie vérifiée : cela n'implique aucune action Docker.
- Si un vrai déploiement échoue, ne pas annoncer un rollback réussi sur la
  seule présence de l'ancien tag : contrôler le Compose actif, la version
  des conteneurs PHP/Nginx, l'image et l'état du worker, puis les journaux
  système **localement sous contrôle d'accès**.
- Conserver les fichiers et données pour diagnostic ; ne pas forcer une
  restauration si un message fournisseur ou une opération métier a pu
  être engagé entretemps.

**No-Go tant que la restauration isolée, la recette message en vol et le
contrôle des sauvegardes ne sont pas documentés et approuvés.**


## 9. Recette SIGTERM avec message en cours (CI isolée)

Le test PHPUnit `testWorkerFinishesInFlightMessageAfterSigterm` utilise
`APP_ENV=test`, MySQL `sylius_test`, les files `yoowii_ci_async` /
`yoowii_ci_failed` et un handler `GracefulStopProbeHandler` **sans effet
métier**. Il démarre le véritable consommateur Symfony Messenger via
`Process`, attend un marqueur attestant que le handler est effectivement
entré dans son traitement, envoie `SIGTERM` pendant le travail simulé
(environ 2 secondes), puis vérifie la fin du handler, l'ACK de la file,
l'absence de message en échec et l'absence de mouvement sur les files
`default` et fournisseur.

Ce test mesure l'arrêt coopératif d'un **processus** Symfony ; il ne prouve
pas le bon comportement de la séquence Docker `stop --time 90` lorsqu'un
handler réel de fichiers est interrompu, ni l'idempotence ou la restauration
du stockage. L'arrêt forcé (SIGKILL après 90 secondes) et le rejeu sans
doublon restent à qualifier à part dans un environnement jetable. Aucun
message ne doit être injecté dans le VPS partagé ou dans une file fournisseur.

**Critère :** ne marquer cette étape validée que si la CI générale exécute
le test et termine avec succès ; tout échec du test doit laisser PR #14 en
brouillon et déclencher une analyse sans modification du VPS.


## 10. Recette de coupure brutale et restauration jetable — CI

- Le test PHPUnit **testInterruptedMessageCanBeRedeliveredInIsolatedDatabase** utilise un handler de démonstration enregistré exclusivement dans APP_ENV=test et des marqueurs temporaires, sans effet métier. Le consommateur Symfony est stoppé par SIGKILL pendant le traitement et le test vérifie que le message Doctrine n'est pas acquitté.
- Le message verrouillé après mort brutale ne devient pas immédiatement disponible. Pour éviter l'attente du délai réel de redelivery, le test réinitialise exclusivement le champ delivered_at sur la file yoowii_ci_async de la base sylius_test. Il **simule ainsi l'expiration de la réservation**, puis relance le consommateur et vérifie l'acquittement au second passage. Ce n'est pas un test du délai de reprise automatique ni de l'idempotence métier.
- Le script **scripts/preprod/test-backup-restore-isolated.sh**, exécuté seulement avec APP_ENV=test, GitHub Actions et opt-in explicite, crée deux bases MySQL fictives sur le runner, produit un dump réel avec mysqldump et le restaure dans la seconde base. Il compare les lignes, puis vérifie la restauration de fichiers de configuration fictifs et de leurs permissions.
- Le job détruit les ressources temporaires et ne lit aucune sauvegarde, base ou volume du VPS.

**Limites :** aucune restauration de sauvegarde Plesk ou MySQL réelle, aucun test de reprise automatique après le délai Doctrine, et aucune démonstration que le handler métier PreflightPrintAsset est idempotent. Maintenir PR #14 en brouillon tant que la sauvegarde/restauration réelle isolée et la recette supervisée du déployeur n'ont pas été réalisées.


## 11. Contrôle d'idempotence du handler de préflight — étape suivante

Un message Messenger Doctrine peut être livré à nouveau après un ACK perdu.
Le handler de préflight vérifie désormais que le rapport correspond à la
même empreinte de fichier et possède encore le statut `pending` avant
d'ouvrir et d'inspecter le document. Si le rapport est déjà
`passed`, `warning` ou `failed`, il sort sans modifier le rapport,
sans nouvel historique et sans flush. Une relance volontaire peut réinitialiser
le rapport avec `restart()`, comme auparavant.

Le test `PreflightPrintAssetHandlerIdempotencyTest` vérifie le rejeu séquentiel
du même message après analyse, le statut final en échec et le rejet
d'un message ancien ne correspondant plus à l'empreinte du rapport.

**Protection concurrente en cours de validation :** le handler traite
maintenant le rapport dans `EntityManagerInterface::wrapInTransaction()`
et appelle `refresh($report, LockMode::PESSIMISTIC_WRITE)` **avant** de
vérifier `pending` et l'empreinte. Le verrou MySQL InnoDB est conservé
pendant l'analyse et jusqu'à la validation du statut et de l'activité dans
la même transaction. Une redélivrance attend le verrou, recharge l'état
persisté et doit ignorer un rapport déjà terminé. Les erreurs de transaction
ne sont pas converties en échec métier silencieux : Messenger peut alors
retenter le message. Le traitement long sous verrou peut cependant causer
une attente ; il faut surveiller les délais/deadlocks.

Le test unitaire simule une première transaction qui a terminé le rapport
pendant l'attente d'un second consommateur. Un test MySQL isolé
`PreflightLockIntegrationTest` vérifie également l'exclusion réelle de
deux connexions InnoDB sur une ligne temporaire de `sylius_test`.
**Limite** : la combinaison de ces preuves n'est pas un stress test
bout-en-bout de deux handlers préflight réels et simultanés avec stockage
partagé. Le worker observé sur VPS est unique, mais un audit de contention
réelle reste conseillé avant montée en charge. Aucun appel fournisseur
n'est réalisé par l'analyse de préflight.


## 12. Inventaire des prérequis de sauvegarde réelle — lecture seule

Le script versionné `scripts/preprod/backup-readiness-readonly.sh` prépare
la restauration d'une vraie sauvegarde Yoowii sans modifier le VPS.
Il contrôle uniquement les propriétaires/modes de fichiers protégés, le
format du tag de release, le nombre de conteneurs applicatifs actifs, la
présence des **trois volumes persistants** définis par Compose
(`mysql_data`, `media`, `private_assets`) et l'accès SQL par une requête
`SELECT 1`. Les valeurs de `.env`, données des messages, contenus des
volumes et identifiants MySQL ne sont jamais affichés.

**Opération administrateur :** télécharger le fichier à partir du commit
épinglé et relire son contenu ; faire `bash -n` puis exécuter
`sudo bash backup-readiness-readonly.sh` manuellement. Ne pas
l'installer comme commande forcée `yoowii-audit` : le compte de diagnostic
reste strictement limité à son script existant. Le script ne produit
aucune sauvegarde. En cas d'anomalie, **aucune correction automatique**.

Pour compléter la preuve de sauvegarde réelle :

1. Depuis Plesk, vérifier la présence d'une sauvegarde couvrant les données
   Yoowii et son horodatage. Ne pas supposer qu'une sauvegarde Plesk du
   domaine inclut les volumes Docker nommés ou la base MySQL contenue dans
   Compose : vérifier explicitement leur couverture.
2. Vérifier le support de sauvegarde, le chiffrement, les droits d'accès,
   la rétention, la fenêtre de cohérence entre base et fichiers ainsi que
   l'espace requis pour restaurer vers un **autre hôte / environnement
   jetable** (sans réutiliser les volumes Docker du VPS).
3. Restaurer **sur l'instance séparée**, avec ports et services externes
   neutralisés. Ne pas utiliser les vraies clés Realisaprint, mail ou Stripe
   dans l'environnement restauré. Vérifier nombre de tables, présence de
   la table `messenger_messages`, cohérence des fichiers privés et
   montages, puis supprimer l'instance selon la politique de rétention.
4. Consigner uniquement les métadonnées non sensibles : version de la
   sauvegarde, couverture (MySQL + volumes), date, durée de restauration,
   checks réussis et responsable de la recette. Pas de dumps, de variables
   d'environnement, de fichiers clients ni de captures SQL dans GitHub.

**IMPORTANT :** la restauration MySQL synthétique réalisée en CI est déjà
verte, mais **ne constitue pas** une preuve que les données réelles Yoowii
sont sauvegardées ou restaurables. Le verdict reste No-Go tant que cette
recette réelle n'est pas validée.


## 13. Drill de restauration fonctionnelle déconnectée

Le script versionné `scripts/preprod/restore-functional-isolated.py` est
destiné **uniquement à une machine jetable, différente du VPS Plesk Yoowii**.
Il refuse de démarrer en root, sans `--ack-isolated-host`, si le chemin
`/opt/yoowii/preprod` existe ou si un conteneur du projet
`yoowii-preprod` est actif.

Prérequis du poste de recette : Docker local utilisable par le compte
non-root, Python 3, espace temporaire suffisant pour décompresser les
archives et importer MySQL, et **une copie** du dossier de sauvegarde
restauré depuis Restic sur le poste de recette. Ne pas faire tourner
le script sur le VPS actif ni dans un environnement partagé avec des
conteneurs clients. Les droits du groupe `docker` sont privilégiés ;
vérifier l'isolation et la politique d'accès au poste jetable.

Exemple **à exécuter sur la machine jetable, et jamais sur le VPS** :

```bash
python3 scripts/preprod/restore-functional-isolated.py \
  --ack-isolated-host \
  --backup-dir /CHEMIN/PROTEGE/2026-11-09_03-02-11
```

Le script valide les empreintes SHA-256 avant toute importation, extrait les
archives médias et fichiers privés dans des répertoires temporaires privés
en refusant les liens et chemins traversants, démarre un conteneur MySQL 8.4
**sans réseau et sans port publié**, importe le dump SQL dans une base dédiée,
vérifie la présence de tables, puis détruit le conteneur et les fichiers
temporaires. Il n'utilise pas le fichier `preprod.env` pour démarrer
MySQL et n'expose aucune clé fournisseur. La CI exerce ce mécanisme avec
**des archives et données entièrement fictives**.

**Preuve encore attendue :** lancer le drill sur le jeu réel restauré
depuis Nextcloud vers un environnement jetable, puis documenter les
résultats sans copier les données, secrets, noms de clients ou archives
dans GitHub. Une extraction/import SQL réussie ne démontre pas encore le
bon démarrage de Sylius ni la cohérence exhaustive des références entre
base et médias. Cette vérification applicative devra être réalisée
séparément, sous un environnement entièrement isolé.
