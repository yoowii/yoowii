# Recette espace client Yoowii — lots 1 à 6

Cette checklist vérifie la refonte UI de l'espace client et les parcours d'impression. Elle ne remplace pas les tests automatisés Symfony, PHP et du build du thème.

## Préparation

- Partir de la branche `fix/account-print-tracking-39` (empilée sur les lots 1–5).
- Activer le thème Yoowii et recompiler ses assets avant la recette.
- Tester avec un compte client propriétaire des commandes et, séparément, un autre compte non propriétaire.
- Préparer une commande non payée, une payée avec article print et une sans article print.
- Préparer des dossiers aux statuts `awaiting_files`, `files_received`, `bat_pending`, `bat_ready`, `bat_approved`, `in_production`, `shipped`, `delivered`, `blocked`, `cancelled`. Utiliser des fixtures ou données de test ; ne pas modifier de commande client réelle.

## Exécutions techniques (depuis la racine du projet)

```bash
# Selon les dépendances installées et la configuration du projet :
yarn build
docker compose run --rm php bin/console lint:twig themes/YoowiiTheme/templates/shop themes/YoowiiTheme/templates/bundles/SyliusShopBundle/account
docker compose run --rm php vendor/bin/phpunit tests/Yoowii/PrintProduction/UI/Http/Storefront/PrintFlowRoutingTest.php
```

Si `lint:twig` ne supporte pas plusieurs chemins sur la version installée, lancer la commande séparément pour chaque dossier. Réaliser ces commandes dans le conteneur PHP/Node configuré par le projet.

## Matrice visuelle

À tester à 375 px, 768 px et 1440 px ; vérifier aussi la navigation clavier, les retours à la ligne et les contrastes.

| Parcours | Attendu |
| --- | --- |
| Tableau de bord | Menu et contenu alignés ; pas de bloc sombre ni de scroll horizontal global |
| Carnet d'adresses | Adresse par défaut lisible ; aucune carte vide ; modifier/supprimer fonctionne |
| Profil / mot de passe | Champs et erreurs lisibles ; validations Symfony inchangées |
| Historique | Commandes, montants, statuts et actions accessibles sur mobile et desktop |
| Détail de commande | Date, devise, état, adresses, paiements et expéditions distincts |
| Mes impressions | Lien de navigation actif ; 0, 1 et plusieurs dossiers correctement affichés |
| Fichiers de commande | Liens vers les bons dossiers, uniquement dans les conditions métier existantes |
| Suivi d'impression | Statut, huit étapes et numéro de suivi visibles selon la donnée disponible |

## Upload et préflight

- Fichier absent, vide, interdit et > 100 Mio : comportement UI et serveur cohérent.
- Case de confirmation non cochée : aucun envoi XHR.
- PDF/JPEG/PNG/TIFF accepté si réellement valide : upload, progression, redirection.
- Erreur 403/413/422, réseau coupé, timeout : erreur compréhensible et nouvel essai possible.
- Contrôle technique : `pending`, `passed`, `warning`, `failed` ; afficher les contrôles sans présenter un avertissement comme un succès.
- Un autre client ne doit pas pouvoir récupérer un asset qui ne lui appartient pas ; conserver les règles d'accès actuelles.

## BAT et suivi

| État | Vérification |
| --- | --- |
| `awaiting_files` | Étape courante = attente de fichiers ; dépôt disponible si autorisé par le domaine |
| `files_received` | Étape courante = fichiers reçus |
| `bat_pending` | Étape courante = BAT en préparation |
| `bat_ready` | BAT visualisable ; approbation/refus et CSRF fonctionnels |
| `bat_approved` | BAT validé, pas de formulaire d'approbation supplémentaire |
| `in_production` | Étape production clairement active |
| `shipped` | Étape expédiée active ; tracking visible seulement s'il existe |
| `delivered` | Étape livrée active, étapes précédentes terminées |
| `blocked` | Alerte spécifique, aucune des 8 étapes présentée comme étape active |
| `cancelled` | Message d'annulation, aucune progression future insinuée |

- Vérifier les messages client et leurs dates ; aucune régression lors d'un refus de BAT.
- Pour les états terminaux, l'indicateur d'étape courant doit être annoncé via `aria-current="step"` uniquement quand il appartient aux huit étapes.
- Cliquer sur le numéro de suivi seulement si une URL de tracking existe et la destination est autorisée.

## Critères de sortie

- [ ] Build thème OK
- [ ] Twig lint et PHPUnit ciblé OK
- [ ] Aucun débordement global 375/768/1440
- [ ] Parcours clavier utilisables, focus visible et `aria-current` cohérent
- [ ] Upload, préflight et BAT vérifiés de bout en bout
- [ ] Contrôle d'accès aux fichiers et routes inchangé
- [ ] Captures avant/après archivées pour validation
