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

    change(event, refreshDelay) {
        const step = event.target.closest(`[data-${this.identifier}-target~="step"]`);
        this.changedAxis = step?.dataset.optionCode || null;

        super.change(event, refreshDelay);
    }

    scheduleRefresh() {
        // The shared controller calls this at connect and after each change.
        // In matrix mode this is intentionally synchronous and network-free.
        this.applyMatrixAvailability(this.changedAxis);
        this.changedAxis = null;
    }

    async refreshProviderState() {
        // Defensive no-op: a matrix configurator must never send a partial
        // configuration to the Realisaprint refresh endpoint.
    }

    applyMatrixAvailability(changedAxis = null) {
        if (!this.hasFormTarget) {
            return;
        }

        const combinations = this.matrixCombinationsValue || [];
        if (combinations.length === 0) {
            return;
        }

        // A matrix configurator is a sequence of decisions. Changing paper,
        // for example, must not keep grammage and quantity as constraints on
        // the paper choices; otherwise a value which is valid for the format
        // is incorrectly left hidden. A new choice clears only its successors.
        this.clearFollowingOptions(changedAxis);
        const selected = this.providerOptions();
        const availability = {};

        this.pricingAxesValue.forEach((axis, axisIndex) => {
            const values = new Set();
            const precedingAxes = this.pricingAxesValue.slice(0, axisIndex);
            combinations.forEach((combination) => {
                const compatible = precedingAxes.every((option) =>
                    selected[option] === undefined || String(combination[option] ?? '') === String(selected[option]),
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

    clearFollowingOptions(changedAxis) {
        const changedIndex = this.pricingAxesValue.indexOf(changedAxis);
        if (changedIndex < 0) {
            return;
        }

        this.pricingAxesValue.slice(changedIndex + 1).forEach((axis) => this.clearOption(axis));
    }

    clearOption(axis) {
        const step = this.optionTargets.find((candidate) => candidate.dataset.optionCode === axis);
        if (!step) {
            return;
        }

        step.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked').forEach((input) => {
            input.checked = false;
        });
        step.querySelectorAll('select').forEach((select) => {
            select.value = '';
        });
    }
}
