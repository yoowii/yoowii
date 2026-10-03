# Lot 10.4 — publication contrôlée d’un produit Realisaprint

Le catalogue Realisaprint reste une copie locale en lecture seule. Il ne crée aucun produit public lors de sa synchronisation.

## Parcours administrateur

1. Dans **Production print → Catalogue Realisaprint**, synchroniser le catalogue puis charger la configuration du produit fournisseur.
2. Choisir **Créer un brouillon Yoowii**. Définir le code `PRINT_*`, les options canoniques et les axes tarifaires. Le produit Sylius, sa variante par défaut, la définition de configurateur et la route Realisaprint sont créés désactivés.
3. Depuis la route Realisaprint, ouvrir **Mapping** et enregistrer une version immuable. Chaque ligne relie une variable API à une option Yoowii et à ses valeurs fournisseur. Le mapping est aussi créé désactivé.
4. Ouvrir **Valider / publier**. La validation vérifie la couverture de tous les axes tarifaires et envoie une seule configuration d’échantillon à l’API Realisaprint. Le mapping reste inactif pendant cette cotation.
5. Publier seulement après un contrôle réussi. La publication active ensemble le produit, sa variante, le configurateur, la référence fournisseur, le mapping et la route.

Une validation expire après 30 minutes. Rejouer le contrôle après toute modification ou à l’expiration.

## Recette manuelle

Avant publication, vérifier que le contrôle indique :

- couverture complète de chaque valeur canonique ;
- cotation API réussie en EUR ;
- coût fournisseur affiché pour la configuration d’échantillon.

Après publication, coter la même configuration dans le storefront puis vérifier que le snapshot porte `source: realisaprint_api` ou `realisaprint_cache`. En cas d’indisponibilité, le repli existant vers une matrice ou une route de secours reste responsable du prix ; aucune commande ou transmission FTP n’est déclenchée par cet assistant.

## Sécurité

Le détail d’échec est limité à 280 caractères et masque les motifs usuels de secret. Les payloads et réponses brutes, identifiants API et fichiers client ne sont jamais sauvegardés par le workflow de publication.
