# Yoowii Theme

`themes/YoowiiTheme` is the storefront theme selected for the Yoowii sales channel.
It is based on the Tailwind CSS 4 and daisyUI 5 storefront published by Adeliom,
then maintained locally by Yoowii.

## Baseline

- Source repository: `agence-adeliom/sylius-tailwindcss-theme`
- Source branch: `2.x`
- Imported commit: `c646967eaddb726fe63b02ce67f75011bae4f9c8`
- Sylius compatibility target: `^2.0`

The source files are intentionally copied into this repository. They are not a
runtime Composer dependency: all Yoowii overrides, print configurators and
supplier integrations remain local and reviewable.

## Activation

Sylius discovers the theme from `themes/YoowiiTheme/composer.json`. To activate
it, edit the Yoowii channel in the administration under `Configuration >
Channels > Look & feel`, then select **Yoowii Theme**. Do not remove the
`app.shop` build: it still loads the Stimulus controllers used by price matrices
and supplier configurators.

## Prescript

The Prescript response is injected into `#api_prescript_wrapper` as supplier
HTML. The Yoowii shell only adds a surrounding layout, responsive containment
and focus styling. Its Stimulus controller observes `#code_liaison` to request
the Yoowii quote, but never renames supplier fields, replaces its markup or
intercepts its events. This keeps the Prescript contract intact while avoiding
polling when the configurator is outside the viewport.

## Matrices et API fournisseur

The standard print configurator keeps its existing form field names, Stimulus
targets and supplier refresh flow. Its Yoowii template turns the same fields
into progressive option cards and a sticky quote summary. The legacy `d-none`
state remains supported inside the theme because the supplier availability
controller uses it to hide conditional options.

## Product page

The product page remains hook-driven by Sylius. The Yoowii overrides provide a
responsive gallery, a sticky product summary and print-aware price/CTA styling
without changing routes, product data or add-to-cart components.

## Catalogue

Taxonomy pages retain Sylius grids, query filters and Pagerfanta pagination.
The Yoowii overrides add responsive product cards, a contained taxon visual, a
sticky taxonomy navigation and daisyUI pagination; no product query, filter
name or pagination route is changed.

## Panier et checkout

Le panier conserve ses composants Live et le tunnel ses routes, formulaires,
étapes et moyens de paiement Sylius. Les surcharges Yoowii se limitent à la
mise en page responsive, aux cartes de récapitulatif et à l'affichage de la
configuration print déjà présente dans le snapshot de l'article.

## Espace client

La connexion, l'inscription et le compte client conservent les contrôleurs et
les formulaires Sylius. Le thème applique des cartes, une navigation compte
fixe sur grand écran et des formulaires cohérents avec le reste du storefront.

## Confirmations et états vides

La confirmation de commande, la consultation de commande, les erreurs 403/404/
500 et les états panier ou grille vides utilisent des cartes légères et des
retours vers la boutique. Les routes et les données de commande restent celles
de Sylius.

## Assets

The theme is built as the `yoowiiTheme` Webpack Encore build and generates its
own manifest under `public/themes/yoowii-theme`. The existing `app.shop` build
remains loaded by the shop hook configuration and therefore keeps the Yoowii
Stimulus controllers for matrix and supplier configurators available.

After pulling this branch in a fully initialized local environment:

```bash
corepack yarn install
corepack yarn build:prod
```

For a focused check of the theme build only:

```bash
./node_modules/.bin/encore production --config themes/YoowiiTheme/webpack.config.js
```

## Upstream maintenance

When upgrading Sylius or importing a newer Adeliom baseline, compare the
templates under `themes/YoowiiTheme/templates/bundles/SyliusShopBundle` with
the matching upstream Sylius ShopBundle templates. Preserve Yoowii templates
under `themes/YoowiiTheme/templates/shop`, especially the print configuration
and checkout-related hooks.
