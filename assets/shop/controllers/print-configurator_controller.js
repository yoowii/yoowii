import { Controller } from '@hotwired/stimulus';

const REFRESH_DEBOUNCE_MILLISECONDS = 400;

export default class extends Controller {
    static targets = [
        'error',
        'form',
        'loading',
        'quoteResult',
        'step',
        'option',
        'summaryItem',
    ];

    static values = {
        hasQuote: Boolean,
        refreshUrl: String,
        pricingAxes: Array,
        initialProviderState: Object,
        initialProviderStateStale: Boolean,
        manualQuote: Boolean,
        debug: Boolean,
    };

    connect() {
        this.abortController = null;
        this.calculationTimer = null;
        this.inputTimer = null;
        this.refreshTimer = null;
        this.refreshSequence = 0;
        this.refreshAbortController = null;
        this.initialStateFrame = null;
        this.refreshPending = false;
        this.lastAutoQuoteFingerprint = null;
        this.quoteRequested = false;
        this.hasUserInteracted = false;
        this.providerStateCache = new Map();
        this.providerState = { visibility: {}, availability: {}, current: {} };
        this.providerVisibility = {};
        if (this.debugValue) {
            console.debug('[print-configurator] initial provider state', {
                hasInitialProviderStateValue: this.hasInitialProviderStateValue,
                initialProviderStateVisibility: this.initialProviderStateValue?.visibility,
                initialProviderStateStaleValue: this.initialProviderStateStaleValue,
            });
        }
        if (this.hasInitialProviderStateValue) {
            this.applyProviderState(this.initialProviderStateValue, { source: 'initial' });
            // A Live Component can finish hydrating immediately after this
            // controller connects. Reapply the server-provided state on the
            // next frame so its DOM replacement cannot expose provider-hidden
            // fields until the customer makes a first choice.
            this.initialStateFrame = window.requestAnimationFrame(() => {
                this.initialStateFrame = null;
                if (!this.hasUserInteracted) {
                    this.applyProviderState(this.initialProviderStateValue, { source: 'initial-dom-ready' });
                }
            });
        } else {
            this.refreshSteps(false);
        }
        // A compatible published snapshot is the authoritative initial state.
        // Do not refresh it on page load: the first customer choice is what
        // supplies the configuration required by show_variables. Refreshing a
        // partial/empty form here can incorrectly reveal conditional fields.
        if (!this.hasInitialProviderStateValue) {
            this.scheduleRefresh();
        }
    }

    disconnect() {
        if (this.initialStateFrame !== null) {
            window.cancelAnimationFrame(this.initialStateFrame);
            this.initialStateFrame = null;
        }
        this.cancelPendingCalculation();
        this.cancelPendingInputChange();
        this.cancelPendingRefresh();
    }

    input(event) {
        // A numeric or free-text value is incomplete while the customer is
        // typing. Do not apply supplier visibility rules to an intermediate
        // value: they may hide the very step being edited.
        this.cancelPendingInputChange();
        this.cancelPendingRefresh();
        this.clearError();
        this.clearQuote();

        this.inputTimer = window.setTimeout(() => {
            this.inputTimer = null;
            this.applyNumericMinimum(event.target);
            this.change(event, 0);
        }, REFRESH_DEBOUNCE_MILLISECONDS);
    }

    change(event, refreshDelay = REFRESH_DEBOUNCE_MILLISECONDS) {
        this.hasUserInteracted = true;
        this.applyNumericMinimum(event.target);
        this.quoteRequested = false;
        const step = event.target.closest('[data-print-configurator-target="step"]');
        const stepIndex = this.stepTargets.indexOf(step);

        this.clearError();
        this.clearQuote();
        this.applyProviderState(this.providerState, { source: 'client', changedStepIndex: stepIndex, applyAvailability: false });
        this.scheduleRefresh(refreshDelay);
    }

    applyNumericMinimum(input) {
        if (!(input instanceof HTMLInputElement) || !['number', 'text'].includes(input.type) || input.min === '') return;
        const value = input.value.trim().replace(',', '.');
        const minimum = Number(input.min);
        if (!Number.isFinite(minimum)) return;
        if (value === '' || !Number.isFinite(Number(value)) || Number(value) < minimum || (input.step === '1' && !/^-?\d+$/.test(value))) {
            input.value = String(minimum);
        }
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

        this.visibleSteps().forEach((step) => {
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

        if (!this.manualQuoteValue && shouldCalculate && previousStepsComplete) {
            this.scheduleCalculation();
        }
        const quoteButton = this.element.querySelector('[data-action="print-configurator#calculate"]');
        if (quoteButton) quoteButton.disabled = this.refreshPending || !this.isComplete();
    }

    visibleSteps() {
        return this.stepTargets.filter((step) => this.isOptionVisible(step.dataset.optionCode) && !step.classList.contains('d-none'));
    }

    summaryForStep(step) {
        return this.summaryItemTargets.find((item) => item.dataset.stepIndex === step.dataset.stepIndex);
    }

    summaryForOption(code) {
        return this.summaryItemTargets.find((item) => item.dataset.optionCode === code);
    }

    isOptionVisible(optionCode) {
        return this.providerVisibility[optionCode] !== false;
    }

    setOptionVisibility(step, visible) {
        step.classList.toggle('d-none', !visible);
        step.setAttribute('aria-hidden', visible ? 'false' : 'true');
        const summaryItem = this.summaryForOption(step.dataset.optionCode);
        summaryItem?.classList.toggle('d-none', !visible);
        summaryItem?.setAttribute('aria-hidden', visible ? 'false' : 'true');
        this.setStepInputsDisabled(step, !visible);
    }

    isSchemaVisible(step) {
        const dependency = step.dataset.dependsOn;
        if (!dependency) return true;
        const [parent, expected] = dependency.split(':');
        const selected = this.formTarget.querySelector(`[name$="[${CSS.escape(parent)}]"]:checked, select[name$="[${CSS.escape(parent)}]"]`);
        return Boolean(selected && selected.value === expected);
    }

    selectedLabels(step) {
        if (!this.isOptionVisible(step.dataset.optionCode)) {
            return [];
        }
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
        if (!this.isOptionVisible(step.dataset.optionCode)) {
            disabled = true;
        }
        step.querySelectorAll('input:not([type="hidden"]), select').forEach((input) => {
            if (disabled) {
                input.disabled = true;

                return;
            }
            input.disabled = input.matches('input[type="radio"], input[type="checkbox"]') && input.closest('.yoowii-print-choice')?.classList.contains('d-none');
        });
    }

    isComplete() {
        const visibleSteps = this.visibleSteps();
        if (visibleSteps.length === 0 || !visibleSteps.every((step) => this.selectedLabels(step).length > 0)) {
            return false;
        }
        return this.pricingAxesValue.every((axis) => {
            if (this.element.querySelector(`[data-fixed-axis="${CSS.escape(axis)}"]`)) {
                return true;
            }
            const axisStep = this.optionTargets.find((step) => step.dataset.optionCode === axis);
            if (axisStep) {
                return !this.isOptionVisible(axis) || axisStep.classList.contains('d-none') || this.selectedLabels(axisStep).length > 0;
            }
            return [...this.formTarget.elements].some((input) => input.name.endsWith(`[${axis}]`) && !input.disabled && input.value !== '' && (input.type !== 'radio' || input.checked));
        });
    }

    scheduleCalculation() {
        this.cancelPendingCalculation();
        this.calculationTimer = window.setTimeout(() => this.calculate(), 250);
    }

    scheduleRefresh(delay = REFRESH_DEBOUNCE_MILLISECONDS) {
        this.cancelPendingRefresh();
        if (!this.hasRefreshUrlValue) {
            return;
        }
        this.refreshTimer = window.setTimeout(() => this.refreshProviderState(), delay);
    }

    async refreshProviderState() {
        if (!this.hasRefreshUrlValue) {
            return;
        }
        this.cancelPendingRefresh();
        const abortController = new AbortController();
        this.refreshAbortController = abortController;
        this.refreshPending = true;
        const sequence = ++this.refreshSequence;
        let refreshSucceeded = false;
        try {
            // Symfony form keys are e.g. print_configurator[quantity]; the API
            // expects canonical option codes without the form name prefix.
            const options = this.providerOptions();
            const cachedState = this.cachedProviderState(options);
            if (cachedState) {
                this.applyProviderState(cachedState, { source: 'browser-cache' });
                refreshSucceeded = true;
                this.scheduleAutomaticQuote();
                return;
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
            this.storeProviderState(options, payload);
            this.applyProviderState(payload);
            // An automatic selection is directly derived from this very
            // show_variables response. Do not immediately call the supplier
            // again just to echo that deterministic correction.
            refreshSucceeded = true;
            this.scheduleAutomaticQuote();
        } catch (error) {
            if (sequence === this.refreshSequence && error.name !== 'AbortError') {
                this.showError(error.message);
            }
        } finally {
            if (this.refreshAbortController === abortController) {
                this.refreshAbortController = null;
                this.refreshPending = false;
                if (refreshSucceeded && this.quoteRequested) {
                    this.quoteRequested = false;
                    this.calculate();
                }
            }
        }
    }

    providerOptions() {
        const options = {};
        for (const [name, value] of new FormData(this.formTarget).entries()) {
            const match = name.match(/\[([^\[\]]+)\]$/);
            if (match && match[1] !== '_token') options[match[1]] = value;
        }

        return options;
    }

    providerStateCacheKey(options) {
        return JSON.stringify(Object.fromEntries(Object.entries(options).sort(([left], [right]) => left.localeCompare(right))));
    }

    providerStateSessionKey(cacheKey) {
        const mappingVersion = this.initialProviderStateValue?.mapping_version;
        const schemaVersion = this.initialProviderStateValue?.schema_version;
        if (!mappingVersion || !schemaVersion) {
            return null;
        }

        return `yoowii:realisaprint:show-variables:${mappingVersion}:${schemaVersion}:${this.refreshUrlValue}:${cacheKey}`;
    }

    cachedProviderState(options) {
        const cacheKey = this.providerStateCacheKey(options);
        const inMemory = this.providerStateCache.get(cacheKey);
        if (inMemory) {
            return inMemory;
        }
        const sessionKey = this.providerStateSessionKey(cacheKey);
        if (!sessionKey) {
            return null;
        }
        try {
            const serialized = window.sessionStorage.getItem(sessionKey);
            if (!serialized) {
                return null;
            }
            const state = JSON.parse(serialized);
            if (!state || typeof state !== 'object') {
                return null;
            }
            this.providerStateCache.set(cacheKey, state);

            return state;
        } catch (_) {
            return null;
        }
    }

    storeProviderState(options, state) {
        const cacheKey = this.providerStateCacheKey(options);
        this.providerStateCache.set(cacheKey, state);
        const sessionKey = this.providerStateSessionKey(cacheKey);
        if (!sessionKey) {
            return;
        }
        try {
            window.sessionStorage.setItem(sessionKey, JSON.stringify(state));
        } catch (_) {
            // Browsers may deny storage in private or constrained contexts.
        }
    }

    scheduleAutomaticQuote() {
        if (!this.manualQuoteValue || !this.hasUserInteracted || !this.isComplete()) {
            return;
        }
        const fingerprint = this.visibleConfigurationFingerprint();
        if (fingerprint !== this.lastAutoQuoteFingerprint) {
            this.lastAutoQuoteFingerprint = fingerprint;
            this.scheduleCalculation();
        }
    }

    applyProviderState(state, { source = 'refresh', changedStepIndex = -1, applyAvailability = true } = {}) {
        this.providerState = state;
        this.providerVisibility = state.visibility || {};
        this.optionTargets.forEach((step) => {
            const code = step.dataset.optionCode;
            const visible = this.isOptionVisible(code) && this.isSchemaVisible(step);
            this.setOptionVisibility(step, visible);
            if (this.debugValue && !this.isOptionVisible(code)) {
                console.debug('[print-configurator] hidden provider option', {
                    definitionCode: code,
                    visibilityKey: code,
                    domElement: step,
                    appliedClass: step.classList.contains('d-none') ? 'd-none' : '',
                    inputsDisabled: [...step.querySelectorAll('input:not([type="hidden"]), select')].every((input) => input.disabled),
                    source,
                });
            }
        });
        let corrected = false;
        if (applyAvailability) {
            Object.entries(state.availability || {}).forEach(([option, availableValues]) => {
                const step = this.optionTargets.find((candidate) => candidate.dataset.optionCode === option);
                if (!step || step.classList.contains('d-none')) return;
                const allowedValues = Array.isArray(availableValues) ? availableValues : [];
                step.querySelectorAll('input[type="radio"]').forEach((input) => {
                    const allowed = allowedValues.includes(input.value);
                    input.closest('.yoowii-print-choice')?.classList.toggle('d-none', !allowed);
                    input.disabled = !allowed;
                });
                step.querySelectorAll('select option').forEach((choice) => {
                    if (choice.value === '') return;
                    const allowed = allowedValues.includes(choice.value);
                    choice.hidden = !allowed;
                    choice.disabled = !allowed;
                });

                const selected = step.querySelector('input[type="radio"]:checked, select');
                const selectedValue = selected?.value || '';
                if (allowedValues.includes(selectedValue)) return;

                // A supplier default/current value is technical state, never a
                // customer selection. Only choose automatically when there is
                // genuinely no alternative left after a refresh.
                if (allowedValues.length !== 1) {
                    step.querySelectorAll('input[type="radio"]:checked').forEach((input) => { input.checked = false; });
                    const select = step.querySelector('select');
                    if (select) select.value = '';
                    corrected = true;
                    return;
                }
                const fallback = allowedValues[0];
                const radio = step.querySelector(`input[type="radio"][value="${CSS.escape(fallback)}"]`);
                if (radio) {
                    radio.checked = true;
                } else {
                    const select = step.querySelector('select');
                    if (select) select.value = fallback;
                }
                corrected = true;
            });
        }
        [...(state.alerts || []), ...(state.infos || [])].forEach((message) => this.showError(message));
        this.refreshSteps(false, changedStepIndex);
        if (corrected) {
            this.clearQuote();
        }
        return corrected;
    }

    async calculate() {
        if (this.refreshPending) {
            this.quoteRequested = true;
            return;
        }
        if (!this.isComplete()) {
            this.showError('Renseignez tous les champs visibles requis avant de calculer le prix.');
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

    visibleConfigurationFingerprint() {
        const options = {};
        for (const [name, value] of new FormData(this.formTarget).entries()) {
            const match = name.match(/\[([^\[\]]+)\]$/);
            if (match && match[1] !== '_token') options[match[1]] = value;
        }
        return JSON.stringify(options);
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

    cancelPendingInputChange() {
        if (this.inputTimer !== null) {
            window.clearTimeout(this.inputTimer);
            this.inputTimer = null;
        }
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
