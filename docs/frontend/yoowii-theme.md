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
