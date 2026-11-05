# Préscript — modèle de menace et plan d'isolation (#2)

**Statut : vulnérabilité non corrigée. Ne pas ouvrir le configurateur Préscript au public tant que les critères ci-dessous ne sont pas remplis.** La recette fonctionnalité (#1) ne remplace pas la recette sécurité.

## Chemin d'exécution actuel

1. `RealisaprintPrescriptTemplateProvider::template()` récupère le document HTML fournisseur via `get_prescript`, et le met en cache. L'identifiant du cache inclut l'URL de rappel stable fournie au fournisseur.
2. `RealisaprintPrescriptMarkupSanitizer::sanitize()` retire les feuilles de style `link` et `style`, puis tente de masquer certaines occurrences d'`api_key`/`apikey` par des expressions régulières. **Ce n'est pas un assainissement HTML/JS de sécurité** : les scripts, attributs d'événement et nombreuses formes d'injection restent possibles.
3. Deux templates rendent la réponse fournisseur avec `{{ prescript_template|raw }}` dans le document de la boutique : `themes/YoowiiTheme/templates/shop/product/show/realisaprint_prescript_configurator.html.twig` et `templates/shop/product/show/realisaprint_prescript_configurator.html.twig`.
4. `assets/shop/controllers/realisaprint-prescript-quote_controller.js` interagit avec le DOM fournisseur et remplace `window.saveConfiguration`. Le script fournisseur s'exécute donc actuellement dans le contexte privilégié de la boutique.
5. Le callback `RealisaprintPrescriptConfigurationController::save()` vérifie un `UriSigner` sur une URL produite pour le produit, mais **la signature d'URL n'est ni un jeton court lié à une session, ni un jeton consommé une seule fois**.

## Risques concrets

- XSS via script ou événement injecté par un document fournisseur ; accès aux informations accessibles au JavaScript du storefront (DOM, jetons, actions client).
- Fuite potentielle de secrets si le masquage regex est contourné ; ne pas considérer le remplacement de chaînes comme une garantie.
- Rejeu d'un callback signé qui n'expire pas ou n'est pas lié à une session et à un mapping.
- Confusion de messages ou de commandes via `window` et callbacks globaux.

## Architecture cible (à implémenter dans un lot de code séparé)

- Servir le document fournisseur à une **origine distincte** du storefront et de l'administration, sans cookie ni secret Yoowii, par exemple une origine d'embed gérée explicitement. **Un iframe sur la même origine avec `allow-scripts` et `allow-same-origin` ne constitue pas une isolation correcte.**
- Encapsuler dans un `iframe sandbox`, avec les seules permissions effectivement indispensables, et définir une CSP restrictive sur **la réponse de l'origine d'embed**, sans élargir la politique globale de la boutique pour le fournisseur.
- Remplacer le pont `window.saveConfiguration` par un protocole `postMessage` minimal, versionné et strictement validé (origine exacte, `event.source === iframe.contentWindow`, type, schéma, tailles, ordre, identifiant de session et anti-rejeu). Vérifier la provenance des réponses et refuser `*` comme cible quand une origine fixe est disponible.
- Faire émettre par le backend un jeton de callback court, aléatoire, à usage unique, lié au produit, à la version de mapping, à la session et à l'opération. Vérifier expiration et consommation atomique côté serveur ; ne jamais injecter de clé fournisseur dans le navigateur.
- Adapter le contrôleur Stimulus : montage/démontage de l'iframe, affichage prix/récapitulatif, erreurs, préservation desktop/mobile. Garder Realisaprint serveur comme source d'autorité pour le prix et l'ajout au panier.

## Recette de sécurité indispensable

- Rejeter un `postMessage` d'origine incorrecte, d'une fenêtre différente, d'un schéma inconnu, trop gros, hors séquence ou rejoué.
- Refuser callback expiré, réutilisé, autre produit, autre mapping ou autre session.
- Tester un HTML fournisseur malveillant (script, `onerror`, requête vers la boutique, tentative d'accès au parent, tentative de vol de cookie/CSRF), et vérifier l'impossibilité d'accéder à l'origine Yoowii.
- Vérifier les parcours nominaux des deux modes (classique et Préscript), le calcul de prix, les quantités et délais, le résumé, le panier, ainsi que les erreurs API. Ne jamais passer de commande fournisseur réelle en recette.
- Confirmer que la désactivation du mode rapide sur production bloque le parcours non sécurisé. Le flag de préproduction n'est pas une validation du mode public.

## Conditions de promotion

**Blocage production maintenu** tant que l'isolation, le callback à usage unique, les tests adversariaux et les tests fonctionnels de bout en bout ne sont pas passés. Il serait dangereux de remplacer simplement `|raw` par un filtrage HTML qui casse les scripts fournisseurs sans apporter l'isolation de privilèges requise.
