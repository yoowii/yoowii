import PrintConfiguratorController from './print-configurator_controller.js';

// The classic Realisaprint configurator has supplier-driven availability.
// Keep a dedicated controller boundary even though the current shared flow
// deliberately reuses the proven base implementation.
export default class extends PrintConfiguratorController {}
