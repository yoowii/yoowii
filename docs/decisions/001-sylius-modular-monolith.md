# ADR 001 — Sylius dans un monolithe modulaire

- Statut : accepté
- Date : 2026-08-27

## Contexte

Yoowii doit vendre des produits imprimés configurables, des packs web, des prestations média et des abonnements. Toutes les lignes payées doivent ensuite être suivies dans Yoowii Flow.

Développer un moteur e-commerce complet ferait porter au projet des fonctions génériques : catalogue, panier, promotions, commandes, clients, paiements, taxes et livraison. À l'inverse, utiliser un CMS e-commerce rendrait plus difficile l'intégration avec les workflows Symfony de Yoowii Flow.

## Décision

Le projet utilise Sylius 2.2 et Symfony 7.4 comme socle. Il reste un monolithe modulaire pendant le MVP.

- Sylius gère le commerce générique.
- Les modules `App\Yoowii` gèrent la configuration, la tarification et l'exécution.
- Le storefront et l'administration Sylius sont réutilisés.
- React est réservé aux interfaces qui bénéficient réellement d'une application interactive.
- MySQL et le transport Doctrine Messenger du squelette sont conservés initialement.

## Conséquences

### Positives

- délai réduit pour le catalogue, le checkout et l'administration ;
- cohérence avec les compétences Symfony de l'équipe ;
- personnalisation possible sans réécrire les fonctions commerciales ;
- un seul déploiement et une seule identité client.

### Contraintes

- les personnalisations Sylius doivent suivre ses points d'extension ;
- les mises à jour Sylius doivent être testées ;
- le métier de production ne doit pas être placé dans les entités Order/Product ;
- l'adoption d'un frontend headless est différée.

## Alternatives écartées

- application e-commerce entièrement sur mesure : coût et risque trop élevés ;
- WooCommerce comme moteur central : intégration moins naturelle avec Yoowii Flow ;
- microservices dès le lancement : complexité opérationnelle injustifiée.
