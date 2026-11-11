# Lot 11.14 — Repli stale-on-error de `show_variables`

Chaque état `show_variables` frais (TTL 30 jours) est désormais dupliqué dans une entrée stale compatible conservée 90 jours.

Lorsqu’une entrée fraîche est absente et que le refresh fournisseur échoue, Yoowii renvoie cet état stale au configurateur plutôt que de propager l’erreur. Un avertissement structuré est écrit dans les logs, sans données de configuration.

Le repli ne s’applique qu’à une clé canonique identique : produit, stock, variables, mapping et schéma doivent correspondre. Une absence de cache stale conserve l’erreur habituelle.

Ce lot sécurise la continuité de service. Le refresh asynchrone immédiat après retour stale nécessitera un worker Messenger distinct afin de ne pas retenir la requête HTTP du client.
