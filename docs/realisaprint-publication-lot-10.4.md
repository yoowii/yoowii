# Lot 10.4 — publication contrôlée d’un produit Realisaprint

Le catalogue Realisaprint reste une copie locale en lecture seule. Il ne crée aucun produit public lors de sa synchronisation.

## Parcours administrateur

1. Dans **Production print → Catalogue Realisaprint**, synchroniser le catalogue puis charger la configuration du produit fournisseur.
2. Choisir **Créer un brouillon Yoowii**. Le code et le nom sont proposés et les options/axes sont préremplis depuis les variables `VARTICLE_*` reçues de Realisaprint. Contrôler seulement le code et le nom ; la section technique avancée reste modifiable si la lecture fournisseur doit être ajustée. Le produit Sylius, sa variante par défaut, la définition de configurateur et la route Realisaprint sont créés désactivés.
3. Depuis la route Realisaprint, ouvrir **Mapping** et enregistrer une version immuable. Le stock, les identifiants `VARTICLE_*` et les codes de valeurs sont préremplis exclusivement depuis la configuration synchronisée ; une quantité libre conserve `{}`. Toutes les variables synchronisées et leurs valeurs doivent être couvertes avant enregistrement. Le mapping est aussi créé désactivé.
4. Ouvrir **Valider / publier**. La validation vérifie la couverture de tous les axes tarifaires et envoie une seule configuration d’échantillon à l’API Realisaprint. Le mapping reste inactif pendant cette cotation.
5. Publier seulement après un contrôle réussi. La publication active ensemble le produit, sa variante, le configurateur, la référence fournisseur, le mapping et la route.

## Configurateur dynamique Realisaprint

La définition publiée conserve les codes canoniques Yoowii, les libellés français, les valeurs visibles, le type Realisaprint, la zone et la position. Les identifiants `VARTICLE_*`, les valeurs numériques fournisseur, `shop_id` et `api_key` ne quittent jamais le serveur. Les libellés de choix entièrement numériques reçoivent un code Yoowii textuel, par exemple `25` devient `value_25`, tout en conservant le code fournisseur `25` dans le mapping.

Après une sélection complète, le storefront appelle le relais Symfony `print-configuration/refresh`. Celui-ci traduit les valeurs canoniques vers le mapping actif, appelle `show_variables` avec `retry=1`, puis retourne seulement la visibilité, les valeurs autorisées, les corrections et les messages normalisés. Les listes et champs non compatibles sont ainsi masqués ou actualisés avant la cotation.

Realisaprint limite chaque fonction à un appel par 15 secondes et par IP. Le navigateur annule les requêtes obsolètes et attend 350 ms après une modification ; le limiteur serveur reste la protection finale. Une erreur de rafraîchissement ne publie pas de prix ni de configuration fournisseur.

Une validation expire après 30 minutes. Rejouer le contrôle après toute modification ou à l’expiration.

## Recette manuelle

Avant publication, vérifier que le contrôle indique :

- couverture complète de chaque valeur canonique ;
- cotation API réussie en EUR ;
- coût fournisseur affiché pour la configuration d’échantillon.

Après publication, coter la même configuration dans le storefront puis vérifier que le snapshot porte `source: realisaprint_api` ou `realisaprint_cache`. En cas d’indisponibilité, le repli existant vers une matrice ou une route de secours reste responsable du prix ; aucune commande ou transmission FTP n’est déclenchée par cet assistant.

## Sécurité

Le détail d’échec est limité à 280 caractères et masque les motifs usuels de secret. Les payloads et réponses brutes, identifiants API et fichiers client ne sont jamais sauvegardés par le workflow de publication.
