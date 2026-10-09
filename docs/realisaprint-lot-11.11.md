# Lot 11.11 — Single-flight Redis du tunnel de devis

Les caches `save_configuration` et `get_price` sont maintenant protégés par le même verrou Redis distribué que `show_variables`.

Lorsqu’un cache miss de prix ou de code fournisseur survient sur plusieurs workers ou instances :

1. un seul worker acquiert le verrou correspondant à la clé de cache ;
2. les autres attendent ;
3. après l’acquisition, chaque worker relit Redis avant de calculer ;
4. seul le premier appelle Realisaprint, les suivants réutilisent sa réponse.

Le verrou est désormais générique (`yoowii.realisaprint.lock.*`) et peut protéger les trois opérations cacheables sans collision entre leurs clés. Les validations de mapping en back-office restent hors cache et ne prennent pas ce chemin.
