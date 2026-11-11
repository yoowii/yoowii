# Lot 11.3 — Cache navigateur des états `show_variables`

Le configurateur conserve chaque état fournisseur normalisé obtenu avec succès dans une `Map` JavaScript. Un retour à une configuration déjà visitée dans la même page applique donc immédiatement l'état connu, sans requête HTTP vers Symfony ni appel Realisaprint.

L'état est aussi conservé dans `sessionStorage` pour la durée de l'onglet. Sa clé contient l'URL de refresh, les valeurs client triées, la version du mapping et la version du schéma. Une nouvelle publication de mapping ou de schéma rend automatiquement les anciennes entrées inaccessibles.

Le stockage navigateur reste une optimisation : s'il est indisponible, le cache mémoire et le cache serveur du lot 11.1 restent utilisables. Les réponses en erreur ne sont jamais stockées.
