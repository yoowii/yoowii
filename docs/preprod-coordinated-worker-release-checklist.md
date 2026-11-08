# Préproduction Yoowii — contrôle avant la recette du déploiement coordonné

> Issue #5 / PR #14. **Lecture seule, aucune commande ci-dessous ne démarre un conteneur ni ne modifie le VPS.** Ne pas remplacer le script root-owned installé tant que cette revue et les tests CI ne sont pas approuvés.

## 1. Prérequis

- Vérifier que la PR #14 est revue, que la CI générale et le test isolé sont verts, et que les sauvegardes/restaurations sont documentées.
- Identifier le script réellement installé à `/usr/local/sbin/yoowii-deploy-preprod` et vérifier son propriétaire et ses permissions. Ne pas le remplacer pendant ce diagnostic.
- Confirmer l'état initial des services avant de prévoir une recette supervisée. Le worker est **opt-in** et ne doit pas être démarré par défaut.
- La préproduction partage un hôte Plesk avec d'autres sites : ne pas exécuter de commandes Docker globales destructrices ou `docker compose down`.

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
