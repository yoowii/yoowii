# Observabilité de la cotation Realisaprint

Le snapshot tarifaire conserve facultativement `quote_trace`. Les snapshots historiques restent valides : le champ est absent pour les cotations antérieures au lot 10.3.1.

Les sources possibles sont `realisaprint_api`, `realisaprint_cache` et `matrix_fallback`. Un fallback inclut un motif normalisé et un identifiant de corrélation. Aucun secret, payload brut ou réponse API brute n'est enregistré dans le snapshot ni affiché par la commande de diagnostic.

## Recette

1. Activer `YOOWII_REALISAPRINT_ENABLED=1` et `YOOWII_REALISAPRINT_QUOTE_ENABLED=1` avec les secrets uniquement dans l'environnement.
2. Générer un devis compatible : `quote_trace.source` vaut `realisaprint_api`, puis `realisaprint_cache` lors d'une seconde cotation identique dans le TTL.
3. Désactiver la cotation ou rendre le mapping incompatible : une matrice reste sélectionnée et `quote_trace.source` vaut `matrix_fallback` avec sa raison.
4. Vérifier la carte « Source de cotation » du dossier de production et les logs structurés avec `correlation_id`.
5. Utiliser `bin/console yoowii:realisaprint:quote:diagnose PRINT_FLYER --options='{"format":"A5"}'`. Ajouter `--no-network` pour contrôler la configuration sans appel fournisseur.

Il n'y a pas de migration : `pricing_snapshot` est déjà une colonne JSON et `quote_trace` est un ajout rétrocompatible.
