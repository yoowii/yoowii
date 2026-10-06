import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'error',
        'form',
        'loading',
        'quoteResult',
        'step',
        'summaryItem',
    ];

    static values = {
        hasQuote: Boolean,
        refreshUrl: String,
        pricingAxes: Array,
        initialProviderState: Object,
    };

    connect() {
        this.abortController = null;
        this.calculationTimer = null;
        this.refreshTimer = null;
        this.refreshSequence = 0;
        this.refreshAbortController = null;
        this.applySchemaVisibility();
        if (this.hasInitialProviderStateValue) this.applyProviderState(this.initialProviderStateValue, true);
        this.refreshSteps(false);
        if (!this.hasInitialProviderStateValue) this.scheduleRefresh();
    }

    disconnect() {
        this.cancelPendingCalculation();
        this.cancelPendingRefresh();
    }

    change(event) {
        const step = event.target.closest('[data-print-configurator-target="step"]');
        const stepIndex = this.stepTargets.indexOf(step);

        this.clearError();
        this.clearQuote();
        this.initialProviderStateValue = null;
        this.applySchemaVisibility();
        // Quote and show_variables are independent requests. Start both once the
        // visible configuration is complete; refreshSequence still discards stale
        // provider responses.
        this.refreshSteps(true, stepIndex);
        this.scheduleRefresh();
    }

    submit(event) {
        event.preventDefault();

        if (this.isComplete()) {
            this.calculate();
        }
    }

    reset() {
        this.cancelPendingCalculation();

        this.formTarget.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach((input) => {
            input.checked = false;
        });
        this.formTarget.querySelectorAll('select').forEach((select) => {
            select.selectedIndex = 0;
        });

        this.clearError();
        this.clearQuote();
        this.refreshSteps(false);
        this.stepTargets[0]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    openStep(event) {
        const stepIndex = Number.parseInt(event.currentTarget.dataset.stepIndex, 10);
        const step = this.stepTargets[stepIndex];

        if (!step || step.classList.contains('is-disabled')) {
            return;
        }

        this.stepTargets.forEach((candidate) => {
            candidate.open = candidate === step;
        });
        step.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    guardStep(event) {
        if (event.currentTarget.closest('details')?.classList.contains('is-disabled')) {
            event.preventDefault();
        }
    }

    refreshSteps(shouldCalculate, changedStepIndex = -1) {
        let previousStepsComplete = true;
        let firstIncompleteStep = null;

        this.stepTargets.filter((step) => !step.classList.contains('d-none')).forEach((step, index) => {
            const enabled = previousStepsComplete;
            const labels = this.selectedLabels(step);
            const complete = labels.length > 0;
            const selectedLabel = labels.join(', ');

            step.classList.toggle('is-disabled', !enabled);
            step.classList.toggle('is-complete', complete);
            this.setStepInputsDisabled(step, !enabled);

            const stepValue = step.querySelector('[data-role="step-value"]');
            if (stepValue) {
                stepValue.textContent = complete ? selectedLabel : this.pendingLabel(step.dataset.stepIndex);
            }

            const summaryItem = this.summaryForStep(step);
            if (summaryItem) {
                summaryItem.classList.toggle('is-complete', complete);
                summaryItem.classList.toggle('is-pending', !complete);

                const summaryValue = summaryItem.querySelector('[data-role="summary-value"]');
                if (summaryValue) {
                    summaryValue.textContent = complete ? selectedLabel : this.pendingLabel(step.dataset.stepIndex);
                }
            }

            if (enabled && !complete && firstIncompleteStep === null) {
                firstIncompleteStep = step;
            }

            previousStepsComplete = previousStepsComplete && complete;
        });

        if (changedStepIndex >= 0) {
            const changedStep = this.stepTargets[changedStepIndex];
            const changedStepIsComplete = changedStep?.classList.contains('is-complete') ?? false;

            this.stepTargets.forEach((step) => {
                step.open = step === firstIncompleteStep;
            });

            if (changedStepIsComplete && firstIncompleteStep) {
                firstIncompleteStep.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else {
            this.stepTargets.forEach((step) => {
                step.open = step === firstIncompleteStep;
            });
        }

        this.visibleSteps().forEach((step, index) => {
            const number = step.querySelector('.yoowii-print-step-number');
            if (number) number.textContent = String(index + 1);
        });

        if (shouldCalculate && previousStepsComplete) {
            this.scheduleCalculation();
        }
    }

    visibleSteps() {
        return this.stepTargets.filter((step) => !step.classList.contains('d-none'));
    }

    summaryForStep(step) {
        return this.summaryItemTargets.find((item) => item.dataset.stepIndex === step.dataset.stepIndex);
    }

    setStepVisibility(step, visible, clearValue = false) {
        step.classList.toggle('d-none', !visible);
        this.summaryForStep(step)?.classList.toggle('d-none', !visible);
        if (!visible && clearValue) {
            step.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach((input) => { input.checked = false; });
            step.querySelectorAll('select').forEach((input) => { input.selectedIndex = -1; });
            step.querySelectorAll('input[type="number"], input[type="text"]').forEach((input) => { input.value = ''; });
        }
        this.setStepInputsDisabled(step, !visible);
    }

    applySchemaVisibility() {
        this.stepTargets.forEach((step) => {
            const dependency = step.dataset.dependsOn;
            if (!dependency) return;
            const [parent, expected] = dependency.split(':');
            const selected = this.formTarget.querySelector(`[name$="[${CSS.escape(parent)}]"]:checked, select[name$="[${CSS.escape(parent)}]"]`);
            const visible = selected && selected.value === expected;
            this.setStepVisibility(step, Boolean(visible), !visible);
        });
    }

    selectedLabels(step) {
        const checkedInputs = [...step.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked')]
            .filter((input) => input.value !== '');
        const selectedOptions = [...step.querySelectorAll('select')]
            .flatMap((select) => [...select.selectedOptions])
            .filter((option) => option.value !== '');
        const textValues = [...step.querySelectorAll('input[type="number"], input[type="text"]')]
            .filter((input) => input.value !== '')
            .map((input) => input.value);

        return [
            ...checkedInputs.map((input) => input.dataset.choiceLabel || input.value),
            ...selectedOptions.map((option) => option.textContent.trim()),
            ...textValues,
        ];
    }

    setStepInputsDisabled(step, disabled) {
        step.querySelectorAll('input:not([type="hidden"]), select').forEach((input) => {
            input.disabled = disabled;
        });
    }

    isComplete() {
        const visibleSteps = this.stepTargets.filter((step) => !step.classList.contains('d-none'));
        if (visibleSteps.length === 0 || !visibleSteps.every((step) => this.selectedLabels(step).length > 0)) {
            return false;
        }
        return this.pricingAxesValue.every((axis) => {
            if (this.element.querySelector(`[data-fixed-axis="${CSS.escape(axis)}"]`)) {
                return true;
            }
            const axisStep = this.stepTargets.find((step) => step.dataset.axis === axis);
            if (axisStep && axisStep.classList.contains('d-none')) {
                return true;
            }
            return [...this.formTarget.elements].some((input) => input.name.endsWith(`[${axis}]`) && !input.disabled && input.value !== '' && (input.type !== 'radio' || input.checked));
        });
    }

    scheduleCalculation() {
        this.cancelPendingCalculation();
        this.calculationTimer = window.setTimeout(() => this.calculate(), 250);
    }

    scheduleRefresh() {
        // An incomplete edit also invalidates any pending supplier response.
        this.cancelPendingRefresh();
        if (!this.hasRefreshUrlValue || !this.isComplete()) {
            return;
        }
        this.refreshTimer = window.setTimeout(() => this.refreshProviderState(), 350);
    }

    async refreshProviderState() {
        if (!this.isComplete() || !this.hasRefreshUrlValue) {
            return;
        }
        this.cancelPendingRefresh();
        const abortController = new AbortController();
        this.refreshAbortController = abortController;
        const sequence = ++this.refreshSequence;
        try {
            // Symfony form keys are e.g. print_configurator[quantity]; the API
            // expects canonical option codes without the form name prefix.
            const options = {};
            for (const [name, value] of new FormData(this.formTarget).entries()) {
                const match = name.match(/\[([^\[\]]+)\]$/);
                if (match && match[1] !== '_token') options[match[1]] = value;
            }
            const response = await fetch(this.refreshUrlValue, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ options }),
                signal: abortController.signal,
            });
            const payload = await response.json();
            if (sequence !== this.refreshSequence) return;
            if (!response.ok) {
                throw new Error(payload.message || 'Les options ne peuvent pas être mises à jour.');
            }
            this.applyProviderState(payload);
        } catch (error) {
            if (sequence === this.refreshSequence && error.name !== 'AbortError') {
                this.showError(error.message);
            }
        } finally {
            if (this.refreshAbortController === abortController) {
                this.refreshAbortController = null;
            }
        }
    }

    applyProviderState(state, initial = false) {
        Object.entries(state.visibility || {}).forEach(([option, visible]) => {
            const step = this.stepTargets.find((candidate) => candidate.dataset.axis === option);
            if (!step) return;
            this.setStepVisibility(step, visible === true, !initial && visible !== true);
        });
        Object.entries(state.values || {}).forEach(([option, values]) => {
            const step = this.stepTargets.find((candidate) => candidate.dataset.axis === option);
            if (!step) return;
            step.querySelectorAll('input[type="radio"]').forEach((input) => {
                const allowed = Object.prototype.hasOwnProperty.call(values, input.value);
                input.closest('.yoowii-print-choice')?.classList.toggle('d-none', !allowed);
                input.disabled = !allowed;
            });
        });
        Object.entries(state.current || {}).forEach(([option, value]) => {
            const input = this.formTarget.querySelector(`[name$="[${CSS.escape(option)}]"][value="${CSS.escape(value)}"]`);
            if (input && !input.checked) input.checked = true;
        });
        [...(state.alerts || []), ...(state.infos || [])].forEach((message) => this.showError(message));
        // The quote request for this edit was already started in parallel with
        // show_variables. Do not send a duplicate quote when the refresh returns.
        this.refreshSteps(false);
    }

    async calculate() {
        if (!this.isComplete()) {
            return;
        }

        this.cancelPendingCalculation();
        const abortController = new AbortController();
        this.abortController = abortController;
        this.setLoading(true);
        this.clearError();

        try {
            const response = await fetch(this.formTarget.action, {
                method: 'POST',
                body: new FormData(this.formTarget),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: abortController.signal,
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'Le tarif ne peut pas être calculé pour cette configuration.');
            }

            this.quoteResultTarget.innerHTML = payload.quote_html;
            this.hasQuoteValue = true;
            this.replaceQuoteToken(payload.quote_token);
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.showError(error.message);
            }
        } finally {
            if (this.abortController === abortController) {
                this.abortController = null;
                this.setLoading(false);
            }
        }
    }

    cancelPendingCalculation() {
        if (this.calculationTimer !== null) {
            window.clearTimeout(this.calculationTimer);
            this.calculationTimer = null;
        }

        this.abortController?.abort();
        this.abortController = null;
        this.setLoading(false);
    }

    cancelPendingRefresh() {
        this.refreshSequence += 1;
        if (this.refreshTimer !== null) {
            window.clearTimeout(this.refreshTimer);
            this.refreshTimer = null;
        }
        this.refreshAbortController?.abort();
        this.refreshAbortController = null;
    }

    clearQuote() {
        this.cancelPendingCalculation();

        if (this.hasQuoteValue || this.quoteResultTarget.querySelector('[data-quote-token]')) {
            this.quoteResultTarget.innerHTML = `<p class="text-secondary mb-0" data-empty-quote>${this.emptyQuoteLabel()}</p>`;
        }

        this.hasQuoteValue = false;
        this.replaceQuoteToken(null);
    }

    replaceQuoteToken(token) {
        const url = new URL(window.location.href);

        if (token) {
            url.searchParams.set('print_quote', token);
            url.hash = 'yoowii-print-configurator';
        } else {
            url.searchParams.delete('print_quote');
            url.hash = '';
        }

        window.history.replaceState({}, '', url);
    }

    setLoading(loading) {
        if (!this.hasLoadingTarget) {
            return;
        }

        this.loadingTarget.classList.toggle('d-none', !loading);
        this.element.setAttribute('aria-busy', loading ? 'true' : 'false');
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.classList.remove('d-none');
    }

    clearError() {
        this.errorTarget.textContent = '';
        this.errorTarget.classList.add('d-none');
    }

    pendingLabel(index) {
        return this.summaryItemTargets[index]?.dataset.pendingLabel || 'À choisir';
    }

    emptyQuoteLabel() {
        return this.quoteResultTarget.querySelector('[data-empty-quote]')?.textContent.trim()
            || this.element.dataset.emptyQuoteLabel
            || 'Le prix final dépend de votre configuration.';
    }
}
