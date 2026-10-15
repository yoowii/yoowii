import { Controller } from '@hotwired/stimulus';

// The supplier script sets input#code_liaison programmatically without emitting
// an event. The bridge observes it without changing supplier markup, fields or
// handlers. It never uses that observation to calculate a price automatically.
export default class extends Controller {
    static values = { quoteUrl: String, csrfToken: String, summaryContext: String, summaryProfile: Object };
    static targets = ['calculate', 'error', 'embed', 'loading', 'quoteResult', 'status'];

    connect() {
        this.configurationCode = null;
        this.pending = false;
        this.quoteAbortController = null;
        this.quoteSequence = 0;
        this.visible = false;
        this.timer = null;
        this.summaryTimer = null;
        this.onEmbedInput = this.scheduleSummary.bind(this);
        this.observeVisibility();
        if (this.hasEmbedTarget) {
            this.observeEmbed();
            this.syncEmbedState();
        }
    }

    disconnect() {
        this.stopPolling();
        this.quoteAbortController?.abort();
        window.clearTimeout(this.summaryTimer);
        this.intersectionObserver?.disconnect();
        this.mutationObserver?.disconnect();
        if (this.hasEmbedTarget) {
            this.embedTarget.removeEventListener('change', this.onEmbedInput, true);
            this.embedTarget.removeEventListener('input', this.onEmbedInput, true);
        }
    }

    observeVisibility() {
        if (!('IntersectionObserver' in window)) {
            this.visible = true;
            this.startPolling();

            return;
        }

        this.intersectionObserver = new IntersectionObserver(([entry]) => {
            this.visible = entry.isIntersecting;

            if (this.visible) {
                this.startPolling();
                this.syncConfiguration();

                return;
            }

            this.stopPolling();
        }, { threshold: 0.01 });
        this.intersectionObserver.observe(this.element);
    }

    observeEmbed() {
        this.mutationObserver = new MutationObserver(() => {
            this.syncEmbedState();
            this.scheduleSummary();
        });
        this.mutationObserver.observe(this.embedTarget, { childList: true, subtree: true });
        this.embedTarget.addEventListener('change', this.onEmbedInput, true);
        this.embedTarget.addEventListener('input', this.onEmbedInput, true);
    }

    startPolling() {
        if (this.timer !== null) {
            return;
        }

        this.timer = window.setInterval(() => this.syncConfiguration(), 350);
    }

    stopPolling() {
        if (this.timer === null) {
            return;
        }

        window.clearInterval(this.timer);
        this.timer = null;
    }

    syncEmbedState() {
        if (!this.hasEmbedTarget) {
            return;
        }

        const isReady = this.embedTarget.children.length > 0;
        this.element.classList.toggle('is-prescript-ready', isReady);
        if (isReady) {
            this.decorateSupplierDocument();
        }

        if (isReady && this.hasStatusTarget) {
            this.statusTarget.classList.add('hidden');
        }
    }

    // Prescript replaces parts of its document while options change. Classes
    // are deliberately additive: no provider element, id, name or handler is
    // renamed or moved, so its legacy scripts keep their contract.
    decorateSupplierDocument() {
        this.embedTarget.classList.add('yoowii-prescript-document');
        this.embedTarget.querySelectorAll('.web_ligne_variable_saisie').forEach((row) => {
            row.classList.add('yoowii-prescript-field');
        });
        this.embedTarget.querySelectorAll('.tableau-prix, .tableaux.tableau-prix').forEach((table) => {
            table.classList.add('yoowii-prescript-price-table');
        });
    }

    syncConfiguration() {
        if (!this.visible) {
            return;
        }

        const field = this.element.querySelector('#code_liaison');
        const code = field?.value?.trim();
        if (!code || code === this.configurationCode) {
            return;
        }

        const hasPreviousConfiguration = this.configurationCode !== null;
        this.configurationCode = code;
        if (hasPreviousConfiguration) {
            this.quoteAbortController?.abort();
            this.clearQuote();
        }
        this.scheduleSummary();
    }

    calculate(event) {
        event?.preventDefault();
        this.syncConfiguration();
        const code = this.configurationCode;
        const quantity = this.quantity();
        if (!code) {
            this.showError('Terminez la configuration fournisseur avant de calculer le prix.');
            return;
        }
        if (!quantity) {
            this.showError('La quantité Préscript sélectionnée est introuvable.');
            return;
        }
        this.requestQuote(code, quantity);
    }

    async requestQuote(code, quantity) {
        if (this.pending) return;
        const abortController = new AbortController();
        this.quoteAbortController = abortController;
        const sequence = ++this.quoteSequence;
        this.pending = true;
        this.setLoading(true);
        try {
            const response = await fetch(this.quoteUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ code, quantity, _token: this.csrfTokenValue }),
                signal: abortController.signal,
            });
            const payload = await response.json();
            if (sequence !== this.quoteSequence) return;
            if (!response.ok || !payload.quote_html) {
                throw new Error(payload.message || 'Le prix Préscript ne peut pas être calculé.');
            }
            this.quoteResultTarget.innerHTML = payload.quote_html;
            this.errorTarget.classList.add('hidden', 'd-none');
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.showError(error.message || 'Le prix Préscript ne peut pas être calculé.');
            }
        } finally {
            if (this.quoteAbortController === abortController) {
                this.quoteAbortController = null;
                this.pending = false;
                this.setLoading(false);
            }
        }
    }

    quantity() {
        const field = this.element.querySelector('[name="quantity" i], [name="quantite" i], #quantity, #quantite');
        const value = field?.value?.trim();
        if (value && /^\d+$/.test(value) && Number(value) > 0) {
            return Number(value);
        }

        // Prescript can render quantities as a price-table radio instead of a
        // named input. Its generated ids end with the selected quantity.
        const selectedPrice = this.element.querySelector('input[name="optionsRadios"]:checked');
        const match = selectedPrice?.id?.match(/_(\d+)$/);

        return match && Number(match[1]) > 0 ? Number(match[1]) : null;
    }

    scheduleSummary() {
        window.clearTimeout(this.summaryTimer);
        this.summaryTimer = window.setTimeout(() => this.publishSummary(), 120);
    }

    publishSummary() {
        const profileSteps = this.profileSteps();
        if (profileSteps !== null) {
            document.dispatchEvent(new CustomEvent('yoowii:configuration-update', {
                detail: { context: this.summaryContextValue, steps: profileSteps },
            }));

            return;
        }

        const steps = [];
        const seen = new Set();
        this.embedTarget.querySelectorAll('.web_ligne_variable_saisie').forEach((row) => {
            const control = this.customerControl(row);
            const label = this.labelFor(row, control);
            const value = this.valueFor(control);
            const code = control?.id || control?.name;
            if (!control || !label || !code || seen.has(code)) return;
            seen.add(code);
            steps.push({ code, label, value, complete: value !== '', required: true });
        });

        const quantity = this.quantity();
        if (quantity && !seen.has('prescript-quantity')) {
            steps.push({ code: 'prescript-quantity', label: 'Quantité', value: String(quantity), complete: true, required: true });
        }

        document.dispatchEvent(new CustomEvent('yoowii:configuration-update', {
            detail: { context: this.summaryContextValue, steps },
        }));
    }

    profileSteps() {
        const fields = this.summaryProfileValue?.version === 'v1' && Array.isArray(this.summaryProfileValue.fields)
            ? this.summaryProfileValue.fields
            : [];
        if (fields.length === 0) return null;

        return fields.map((field) => {
            const control = this.profileControl(field);
            const value = this.valueFor(control);

            return {
                code: field.code,
                label: field.label,
                value,
                complete: value !== '',
                required: field.required !== false,
            };
        });
    }

    profileControl(field) {
        if (field.input_id) {
            const control = this.embedTarget.querySelector(`#${CSS.escape(field.input_id)}`);
            if (control) return control;
        }
        if (!field.input_name) return null;

        const controls = [...this.embedTarget.querySelectorAll(`[name="${CSS.escape(field.input_name)}"]`)];
        return controls.find((control) => (control.type === 'radio' || control.type === 'checkbox') && control.checked)
            || controls.find((control) => control.type !== 'hidden')
            || null;
    }

    customerControl(row) {
        return [...row.querySelectorAll('select, textarea, input:not([type="hidden"])')].find((control) => {
            if (control.disabled || control.classList.contains('classs_usage_reserve') || control.classList.contains('class_usage_reserve')) {
                return false;
            }
            if (control.type === 'radio' || control.type === 'checkbox') {
                return control.checked;
            }
            return control.getClientRects().length > 0;
        });
    }

    labelFor(row, control) {
        const explicit = row.querySelector('.web_ligne_variable_saisie_intitule, label[for]');
        if (explicit?.textContent?.trim()) return explicit.textContent.trim();
        const labelledBy = control?.getAttribute('aria-labelledby');
        const label = labelledBy ? this.embedTarget.querySelector(`#${CSS.escape(labelledBy)}`) : null;

        return label?.textContent?.trim() || '';
    }

    valueFor(control) {
        if (!control) return '';
        if (control instanceof HTMLSelectElement) {
            return control.selectedOptions[0]?.textContent?.trim() || '';
        }
        if (control.type === 'radio' || control.type === 'checkbox') {
            if (!control.checked) return '';
            const label = this.embedTarget.querySelector(`label[for="${CSS.escape(control.id)}"]`);
            return label?.textContent?.trim() || control.value?.trim() || '';
        }

        return control.value?.trim() || '';
    }

    clearQuote() {
        if (this.hasQuoteResultTarget) {
            this.quoteResultTarget.innerHTML = '<p class="text-base-content/70" data-empty-quote>Le prix final dépend de votre configuration.</p>';
        }
    }

    setLoading(loading) {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.toggle('hidden', !loading);
        }
        if (this.hasCalculateTarget) {
            this.calculateTarget.toggleAttribute('disabled', loading);
        }
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.classList.remove('hidden', 'd-none');
    }
}
