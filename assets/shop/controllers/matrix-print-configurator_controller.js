import PrintConfiguratorController from './print-configurator_controller.js';

// Matrix pricing keeps its own Stimulus entry point. Its behaviour is shared
// with the base controller, while mode-specific interactions can evolve here
// without coupling them to Realisaprint's classic API flow.
export default class extends PrintConfiguratorController {}
