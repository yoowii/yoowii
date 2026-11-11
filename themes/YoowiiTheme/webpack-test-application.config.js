const path = require('path');

// @tailwindcss/node uses process.env.NODE_PATH to build its CSS resolver (enhanced-resolve).
// Without this, it can't find 'tailwindcss' because node_modules lives in the test-application
// directory, not in any ancestor of the theme CSS files.
const testAppNodeModules = path.resolve(__dirname, '../../vendor/sylius/test-application/node_modules');
process.env.NODE_PATH = testAppNodeModules;

const Encore = require(require.resolve('@symfony/webpack-encore', {
    paths: [path.resolve(__dirname, '../../vendor/sylius/test-application/node_modules')]
}));

Encore
  .setOutputPath('public/themes/yoowii-theme')
  .setPublicPath('/themes/yoowii-theme')
  .addEntry('app', '../../../themes/YoowiiTheme/assets/index.js')
  .enableSassLoader()
  .enablePostCssLoader((options) => {
    options.postcssOptions = {
      config: false,
      plugins: [
        ['@tailwindcss/postcss', {}],
      ],
    };
  })
  .disableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableIntegrityHashes()
  .enableSassLoader()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
;

const config = Encore.getWebpackConfig();
config.name = 'yoowiiTheme';

module.exports = config;
