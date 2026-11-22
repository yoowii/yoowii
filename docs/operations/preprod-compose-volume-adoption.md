# Préprod — adoption des volumes existants et warning du worker (#52)

## Pourquoi
Les volumes Docker `yoowii-preprod_media` et `yoowii-preprod_private_assets` existent déjà mais ne sont pas étiquetés comme créés par le Compose actuel. Une déclaration de volume interne provoque un avertissement à chaque `up` / rollback. Le service `worker` est un overlay opt-in légitime et apparaît comme « orphan » lorsque la commande utilise seulement le Compose principal.

## Correctifs
- `compose.preprod.yml` : ces **deux** volumes sont `external: true` avec leurs noms Docker existants et inchangés. `mysql_data` n'est pas modifié.
- `deploy-vps.sh` : garde-fou sur les changements de volumes, existence effective des volumes, correspondance des montages du PHP avec ces noms. `COMPOSE_IGNORE_ORPHANS=true` supprime uniquement l'avertissement ; le script continue de détecter, d'arrêter et de réconcilier le worker existant lors du déploiement et du rollback.

## Déploiement coordonné obligatoire
**Ne pas fusionner la PR sans installer au préalable le nouveau déployeur root-owned** sur le VPS. L'ancien `/usr/local/sbin/yoowii-deploy-preprod` refuse toute modification top-level des volumes. Le dépôt GitHub ne met pas à jour ce script sur le VPS.

### Vérifications en lecture seule avant toute opération
Sur le VPS, sans montrer les secrets :
```bash
cd /opt/yoowii/preprod
docker volume inspect --format '{{.Name}}' yoowii-preprod_media yoowii-preprod_private_assets
docker inspect --format '{{range .Mounts}}{{.Destination}}={{.Name}}{{println}}{{end}}' yoowii-preprod-php-1
docker inspect --format '{{range .Mounts}}{{.Destination}}={{.Name}}{{println}}{{end}}' yoowii-preprod-nginx-1
docker ps -a --filter label=com.docker.compose.project=yoowii-preprod --filter label=com.docker.compose.service=worker --format '{{.Names}} {{.Image}} {{.Status}}'
stat -c '%U:%G %a %n' /usr/local/sbin/yoowii-deploy-preprod /opt/yoowii/preprod/compose.preprod.yml
```
Les montages doivent correspondre exactement à `/srv/sylius/public/media=yoowii-preprod_media` et `/srv/sylius/var/private/print=yoowii-preprod_private_assets` pour PHP. Ne pas procéder si le contrôle échoue.

### Installation contrôlée
1. Valider la PR et les tests de syntaxe / rollback isolé.
2. Sauvegarder le déployeur actuel dans un fichier root-only, hors du dépôt Git (le restaurer en cas de problème). Vérifier l'intégrité du fichier de remplacement récupéré depuis un SHA immuable de la PR, **pas** via `curl | bash`.
3. `bash -n` et revue du nouveau `deploy-vps.sh` puis installation par `install -o root -g root -m 0755` sur `/usr/local/sbin/yoowii-deploy-preprod`.
4. Seulement ensuite fusionner la PR dans `preprod` : le build publiera une nouvelle paire PHP/Nginx et proposera une release. Autoriser le déploiement via la procédure humaine habituelle.
5. Contrôler les SHA PHP/Nginx/worker, le statut du worker, les mounts, HTTP/HTTPS 200, et l'absence des trois avertissements.

### En cas d'échec
Le rollback du déployeur restaure les images et le Compose précédent, pas les données (qui ne doivent pas changer). En cas de problème avant promotion, restaurer l'ancien script root-owned depuis la copie conservée. Si le nouveau Compose a déjà été promu, **ne pas** réinstaller un vieux déployeur incompatible avant d'avoir rétabli le Compose ancien.

Interdit : `docker compose down -v`, `docker volume rm`, `docker system prune`, `--remove-orphans`, nettoyage de files Messenger et toute manipulation globale sur le VPS Plesk.
