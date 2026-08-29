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
    };

    connect() {
        this.abortController = null;
        this.calculationTimer = null;
        this.refreshSteps(false);
    }

    disconnect() {
        this.cancelPendingCalculation();
    }

    change(event) {
        const step = event.target.closest('[data-print-configurator-target="step"]');
        const stepIndex = this.stepTargets.indexOf(step);

        this.clearError();
        this.clearQuote();
        this.refreshSteps(true, stepIndex);
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

        this.stepTargets.forEach((step, index) => {
            const enabled = previousStepsComplete;
            const labels = this.selectedLabels(step);
            const complete = labels.length > 0;
            const selectedLabel = labels.join(', ');

            step.classList.toggle('is-disabled', !enabled);
            step.classList.toggle('is-complete', complete);
            this.setStepInputsDisabled(step, !enabled);

            const stepValue = step.querySelector('[data-role="step-value"]');
            if (stepValue) {
                stepValue.textContent = complete ? selectedLabel : this.pendingLabel(index);
            }

            const summaryItem = this.summaryItemTargets[index];
            if (summaryItem) {
                summaryItem.classList.toggle('is-complete', complete);
                summaryItem.classList.toggle('is-pending', !complete);

                const summaryValue = summaryItem.querySelector('[data-role="summary-value"]');
                if (summaryValue) {
                    summaryValue.textContent = complete ? selectedLabel : this.pendingLabel(index);
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

        if (shouldCalculate && previousStepsComplete) {
            this.scheduleCalculation();
        }
    }

    selectedLabels(step) {
        const checkedInputs = [...step.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked')]
            .filter((input) => input.value !== '');
        const selectedOptions = [...step.querySelectorAll('select')]
            .flatMap((select) => [...select.selectedOptions])
            .filter((option) => option.value !== '');

        return [
            ...checkedInputs.map((input) => input.dataset.choiceLabel || input.value),
            ...selectedOptions.map((option) => option.textContent.trim()),
        ];
    }

    setStepInputsDisabled(step, disabled) {
        step.querySelectorAll('input:not([type="hidden"]), select').forEach((input) => {
            input.disabled = disabled;
        });
    }

    isComplete() {
        return this.stepTargets.length > 0 && this.stepTargets.every((step) => this.selectedLabels(step).length > 0);
    }

    scheduleCalculation() {
        this.cancelPendingCalculation();
        this.calculationTimer = window.setTimeout(() => this.calculate(), 250);
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
