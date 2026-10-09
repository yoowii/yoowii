# Lot 11.7 — Debounce uniforme du configurateur

Chaque changement de configuration qui peut déclencher `show_variables` passe désormais par un debounce de 400 ms.

- Les listes, boutons radio et cases à cocher attendent 400 ms après le dernier changement.
- Les champs numériques ou texte attendent également 400 ms après la dernière frappe ; ils ne cumulent plus un délai de saisie puis un second délai de refresh.
- Un changement supplémentaire annule le timer et toute requête de refresh encore en cours.

Le délai reste suffisamment court pour garder un configurateur réactif, tout en supprimant les séquences de changements intermédiaires qui seraient de toute façon annulées par l'utilisateur. Il réduit donc la pression sur le cache, le verrou Redis et le throttle global ajoutés dans les lots précédents.

Le test Stimulus couvre explicitement le délai unique de 400 ms. Il corrige également une attente devenue obsolète depuis le lot 11.2 : l'auto-correction ne déclenche plus de second refresh et ne conserve donc plus de variable `corrected` dans la méthode de refresh.
