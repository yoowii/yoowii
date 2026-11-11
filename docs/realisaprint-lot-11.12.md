# Lot 11.12 — Popularité opaque des configurations Realisaprint

Après chaque devis API Realisaprint valide, Yoowii incrémente un Sorted Set Redis de popularité.

- Le membre est un hash SHA-256 du fingerprint canonique et de la version de mapping.
- Aucune option, texte libre, identité client, référence de session ou montant n’est enregistré dans ce classement.
- Le classement est borné aux 1 000 membres les plus populaires ; les entrées les moins utilisées sont écartées.
- Une indisponibilité Redis lors de ce suivi est journalisée mais n’interrompt jamais le devis.

Ce signal sert de base au lot de préchauffage : celui-ci devra uniquement réconcilier les hashes populaires avec des scénarios catalogue explicitement approuvés, sans tenter de reconstituer ni conserver les saisies utilisateur.
