import PrintConfiguratorController from './print-configurator_controller.js';

// Matrix pricing never calls show_variables. Its published rows are a finite
// compatibility table, so value availability can be recalculated locally for
// every partial customer selection.
export default class extends PrintConfiguratorController {
    static values = {
        ...PrintConfiguratorController.values,
        matrixCombinations: Array,
    };

    connect() {
        super.connect();
        this.applyMatrixAvailability();
    }

    scheduleRefresh() {
        // The shared controller calls this at connect and after each change.
        // In matrix mode this is intentionally synchronous and network-free.
        this.applyMatrixAvailability();
    }

    async refreshProviderState() {
        // Defensive no-op: a matrix configurator must never send a partial
        // configuration to the Realisaprint refresh endpoint.
    }

    applyMatrixAvailability() {
        if (!this.hasFormTarget) {
            return;
        }

        const combinations = this.matrixCombinationsValue || [];
        if (combinations.length === 0) {
            return;
        }

        const selected = this.providerOptions();
        const availability = {};

        this.pricingAxesValue.forEach((axis) => {
            const values = new Set();
            combinations.forEach((combination) => {
                const compatible = Object.entries(selected).every(([option, value]) =>
                    option === axis || String(combination[option] ?? '') === String(value),
                );
                const candidate = combination[axis];
                if (compatible && candidate !== undefined) {
                    values.add(String(candidate));
                }
            });
            availability[axis] = [...values];
        });

        this.applyProviderState({
            visibility: Object.fromEntries(this.pricingAxesValue.map((axis) => [axis, true])),
            availability,
            current: {},
            alerts: [],
            infos: [],
        }, { source: 'matrix', changedStepIndex: -1 });
    }
}
