const Encore = require('@symfony/webpack-encore');

Encore
  .setOutputPath('public/themes/yoowii-theme')
  .setPublicPath('/themes/yoowii-theme')
  .addEntry('app', './themes/YoowiiTheme/assets/index.js')
  .copyFiles({
        from: './themes/YoowiiTheme/assets/media',
        to: 'media/[path][name].[hash:8].[ext]',
        pattern: /\.(png|jpe?g|gif|svg|webp)$/
  })
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
