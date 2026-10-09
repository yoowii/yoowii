# Lot 11.2 — Éviter le double refresh après auto-correction

Lorsqu'un état `show_variables` rend une valeur unique obligatoire, le configurateur la sélectionne automatiquement. Cette sélection provient déjà de la réponse fournisseur qui vient d'être reçue : relancer immédiatement `show_variables` avec cette même correction produisait un second appel inutile et pouvait déclencher la limite de 15 secondes.

Le contrôleur Stimulus applique maintenant cette correction, actualise le récapitulatif puis poursuit normalement le cycle de succès. La cotation automatique reste possible si la configuration devient complète, mais aucune nouvelle interrogation fournisseur n'est planifiée tant que le client ne réalise pas une nouvelle action.

Le test `PrintConfiguratorStimulusTest::testAutomaticCorrectionsDoNotTriggerASecondProviderRefresh` protège cette règle.
