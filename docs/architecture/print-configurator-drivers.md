# Drivers de configurateur print

## Objectif

Un produit print Yoowii peut être sourcé chez plusieurs fournisseurs en back-office, mais le client ne configure qu'une seule offre à la fois. Le driver de configurateur sépare :

- la décision de sourcing : quelle route prioritaire et publiable est retenue ;
- le moteur de configuration : matrice locale, API fournisseur ou document fournisseur ;
- la présentation storefront : contrôleur Stimulus, template et comportements UI ;
- le calcul et le snapshot immuable qui permettront ensuite de produire la commande.

Cette séparation évite qu'un correctif Prescript, Matrice ou API classique modifie le comportement des deux autres moteurs.

## Flux storefront

```text
Produit Sylius print
  -> StorefrontPrintSourcingResolver
  -> première route active et publiable, par priorité croissante
  -> code de driver
  -> PrintConfiguratorDriverRegistry
  -> template / contrôleur Stimulus du moteur
  -> devis verrouillé sur cette route
  -> snapshot panier et commande
```

Une route API n'est publiable que si son mapping est actif, effectif, publié et non archivé. Si cette condition n'est pas satisfaite, la route est ignorée et la route de priorité suivante est évaluée. Une route Matrice ou Manuelle utilise le driver `matrix`.

Le sourcing est résolu avant le rendu. Le navigateur ne teste pas plusieurs fournisseurs et ne décide pas de basculer de lui-même.

## Drivers actuels

| Code mapping | Classe | Présentation | Responsabilité |
|---|---|---|---|
| `matrix` | `MatrixPrintConfiguratorDriver` | `matrix-print` | Compatibilités calculées localement depuis les lignes de matrice ; aucune requête `show_variables`. |
| `realisaprint_classic` | `RealisaprintClassicConfiguratorDriver` | `realisaprint-classic` | Rafraîchissement des disponibilités et devis via l'API Realisaprint classique. |
| `realisaprint_prescript` | `RealisaprintPrescriptConfiguratorDriver` | `realisaprint-prescript` | Document HTML Prescript isolé, adaptation Yoowii et endpoints Prescript dédiés. |

Les classes se trouvent dans `src/Yoowii/Pricing/Application/Configurator/`. Le registre explicite est `PrintConfiguratorDriverRegistry`.

Chaque driver expose cinq informations :

- son code stable ;
- son mode de présentation ;
- s'il utilise un rafraîchissement fournisseur ;
- s'il utilise les combinaisons de matrice locales ;
- s'il utilise un document fournisseur embarqué.

Le shell Twig commun reste `themes/YoowiiTheme/templates/shop/product/show/print_configurator.html.twig`. Un driver utilisant un document fournisseur peut conserver un template distinct, comme Prescript.

## Déclaration dans un mapping

Le nouveau champ recommandé est `yoowii.configurator_driver` dans `configuration_mapping` :

```json
{
  "yoowii": {
    "configurator_driver": "realisaprint_classic"
  },
  "realisaprint": {
    "configurator": "classic",
    "product": "1234",
    "stock": "5678"
  }
}
```

Pour préserver les mappings existants, l'absence de ce champ applique une compatibilité historique :

- pas de mapping fournisseur : `matrix` ;
- mapping Realisaprint classique : `realisaprint_classic` ;
- mapping Realisaprint `prescript` : `realisaprint_prescript`.

Un code déclaré mais absent de `PrintConfiguratorDriverRegistry` rend le mapping inéligible pour le storefront. La route suivante peut alors être retenue. Cette règle évite de publier par erreur un mapping dont le moteur n'est pas déployé.

## Définition de configurateur par route

Un mapping peut référencer une `printProductDefinition`. Lorsqu'elle existe, elle devient la définition réellement utilisée par la route résolue : schéma des champs, axes tarifaires et code inscrit dans le devis/snapshot. `Product::printDefinitionCode` reste un profil par défaut de compatibilité, notamment pour les routes matrice sans mapping ; il ne désigne pas le fournisseur.

Ainsi, un seul produit commercial Sylius peut conserver son URL, ses contenus et sa variante tout en utilisant une définition Realisaprint, Laboprint ou matrice différente selon la route prioritaire publiée. La nouvelle définition est déterminée avant le rendu. Un devis déjà émis ne bascule jamais vers une autre route ou définition.

## Contrat d'immuabilité

Une fois le produit affiché, le devis est calculé uniquement avec la route sélectionnée. Il ne tente pas un autre fournisseur si l'API échoue. Le client reçoit une erreur contrôlée et peut recommencer une configuration ultérieurement, après une décision back-office éventuelle.

Le `PricingSnapshot` stocke déjà fournisseur, référence fournisseur et version de mapping ou de matrice selon le moteur. Il contient également :

```json
{
  "configuration": {
    "sourcing": {
      "route_id": 42,
      "route_priority": 10
    }
  }
}
```

La désactivation d'une route ou le changement de priorité ne concerne donc que les nouvelles configurations. Un devis, panier ou une commande existants restent associés à leur sourcing d'origine.

## Mode d'emploi : ajouter un fournisseur API

L'intégration doit être faite dans cet ordre.

### 1. Définir le contrat fournisseur

- Identifier les options canoniques Yoowii, les options obligatoires, les dépendances, la quantité réelle, les délais et les erreurs récupérables.
- Définir ce qui appartient à la configuration cliente, au mapping serveur et au snapshot.
- Ne jamais transmettre au navigateur les identifiants secrets, coûts fournisseurs ou règles de marge.

### 2. Créer le driver

Créer une classe dans `src/Yoowii/Pricing/Application/Configurator/` qui implémente `PrintConfiguratorDriver`.

Choisir un code pérenne, par exemple `laboprint_api`. Déclarer précisément ses capacités : un driver API avec rafraîchissement fournisseur retournera `true` pour `usesSupplierRefresh()`, tandis qu'un moteur local basé sur une matrice retournera `true` pour `usesMatrixCombinations()`.

Ne réutiliser un driver existant que si le contrat UI et technique est réellement identique. Un nouvel API avec un payload, des dépendances d'options ou une réponse différente mérite son propre driver.

### 3. Enregistrer le driver

Injecter le nouveau driver dans `PrintConfiguratorDriverRegistry` et l'ajouter à la table `$drivers` :

```php
$laboprintApi->code() => $laboprintApi,
```

Ajouter des tests du registre couvrant le code, le mode de présentation et les capacités annoncées.

### 4. Déclarer le driver dans le mapping publié

Ajouter le code au mapping du produit fournisseur :

```json
{
  "yoowii": {
    "configurator_driver": "laboprint_api"
  }
}
```

La publication doit refuser ou signaler un mapping dont le code n'est pas installé. Vérifier dans le cockpit `/admin/print-sourcing/products/PRINT_*` que la route apparaît comme **Utilisée** ou **Secours**, jamais comme **Ignorée**.

Choisir aussi, dans le formulaire de mapping, la définition de configurateur propre à cette route si son schéma diffère du profil Sylius par défaut. Cette sélection est ce qui permet de faire évoluer un même produit commercial vers une API dont les options ne sont pas celles de Realisaprint.

### 5. Brancher les endpoints serveur dédiés

- Créer un adaptateur fournisseur côté infrastructure ; il encapsule HTTP, authentification, délais, reprise et normalisation des erreurs.
- Créer les cas d'usage application : rafraîchissement des options, devis et éventuellement ajout panier/document fournisseur.
- Ajouter des routes storefront propres au fournisseur ou au driver. Elles doivent vérifier le sourcing résolu et le driver avant tout appel externe.
- Calculer prix, marge et snapshot uniquement côté serveur.
- Ne pas modifier les routes ou le payload Realisaprint pour intégrer un autre fournisseur.

### 6. Brancher le template et le contrôleur Stimulus dédiés

- Ajouter un contrôleur dans `assets/shop/controllers/`, par exemple `laboprint-api-configurator_controller.js`.
- Conserver le résumé Yoowii, les états de chargement, l'accessibilité et le comportement mobile communs.
- Isoler les règles propres au fournisseur : ordre des champs, compatibilités, appels de rafraîchissement, messages et formats de réponse.
- Si le fournisseur fournit du HTML, le traiter comme Prescript : assainissement serveur, périmètre CSS/JS strict et aucun script externe non maîtrisé.

### 7. Verrouiller et tester le parcours

Tester au minimum :

1. route active et mapping publié : le bon driver est rendu ;
2. mapping désactivé, archivé ou driver absent : la route de secours est sélectionnée ;
3. options incompatibles : le driver ne produit pas un devis incohérent ;
4. erreur API : pas de bascule silencieuse vers un autre fournisseur ;
5. devis, panier puis commande : le snapshot conserve le sourcing choisi ;
6. produit Matrice, API classique et Prescript : absence de régression.

## Règles à préserver

- Un produit storefront utilise un seul sourcing résolu à la fois.
- Un devis ne re-route jamais une configuration déjà commencée.
- Le multi-fournisseur reste une capacité de sourcing back-office, pas un choix caché dans le navigateur.
- Les snapshots historiques sont immuables.
- Chaque nouveau driver doit avoir ses propres tests unitaires et, dès qu'il contacte une API, des tests d'intégration/fonctionnels.
