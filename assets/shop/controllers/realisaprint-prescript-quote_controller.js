import { Controller } from '@hotwired/stimulus';

// The supplier script sets input#code_liaison programmatically without emitting
// an event. The bridge observes it without changing supplier markup, fields or
// handlers. It never uses that observation to calculate a price automatically.
export default class extends Controller {
    static values = { quoteUrl: String, csrfToken: String, summaryContext: String };
    static targets = ['addToCart', 'error', 'embed', 'loading', 'options', 'pricing', 'quoteResult', 'quoteStale', 'status', 'supplierCalculate'];

    connect() {
        this.configurationCode = null;
        this.pending = false;
        this.quoteAbortController = null;
        this.quoteSequence = 0;
        this.quoteStale = false;
        this.visible = false;
        this.timer = null;
        this.summaryTimer = null;
        this.galleryTimer = null;
        this.gallerySignature = null;
        this.onEmbedInput = this.scheduleSummary.bind(this);
        this.onSupplierInput = this.onSupplierInput.bind(this);
        this.onOptionCardClick = this.onOptionCardClick.bind(this);
        this.observeVisibility();
        if (this.hasEmbedTarget) {
            this.observeEmbed();
            this.syncEmbedState();
        }
        this.configurationScope().addEventListener('change', this.onSupplierInput, true);
        this.configurationScope().addEventListener('click', this.onOptionCardClick);
    }

    disconnect() {
        this.stopPolling();
        this.quoteAbortController?.abort();
        window.clearTimeout(this.summaryTimer);
        window.clearTimeout(this.galleryTimer);
        this.intersectionObserver?.disconnect();
        this.mutationObserver?.disconnect();
        this.pricingObserver?.disconnect();
        if (this.hasEmbedTarget) {
            this.embedTarget.removeEventListener('change', this.onEmbedInput, true);
            this.embedTarget.removeEventListener('input', this.onEmbedInput, true);
        }
        this.configurationScope().removeEventListener('change', this.onSupplierInput, true);
        this.configurationScope().removeEventListener('click', this.onOptionCardClick);
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
            this.scheduleGalleryUpdate();
        });
        this.mutationObserver.observe(this.embedTarget, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['src'],
        });
        this.embedTarget.addEventListener('change', this.onEmbedInput, true);
        this.embedTarget.addEventListener('input', this.onEmbedInput, true);
        if (this.hasPricingTarget) {
            this.pricingObserver = new MutationObserver(() => this.refreshPurchaseAction());
            this.pricingObserver.observe(this.pricingTarget, { childList: true, subtree: true });
        }
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
            this.assembleSupplierDocument();
            this.scheduleGalleryUpdate();
            this.syncOptionCardStates();
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
        this.embedTarget.querySelectorAll('.web_ligne_variable_saisie').forEach((row) => {
            this.decorateSupplierTooltip(row);
            this.disableUnavailableSupplierControls(row);
            this.decorateOptionCard(row);
        });
    }

    // Supplier tooltip markup is retained, but its former Bulletip stylesheet
    // is intentionally not loaded. These classes give the local stylesheet a
    // stable and accessible hook for the image and description.
    decorateSupplierTooltip(row) {
        const tooltip = row.querySelector('.infobulle_css');
        const trigger = tooltip?.querySelector('.bulletip-item');
        const content = tooltip?.querySelector('.infobulle_css_contenu');
        if (!tooltip || !trigger || !content) return;

        tooltip.classList.remove('hidden');
        tooltip.classList.add('yoowii-prescript-tooltip');
        trigger.classList.add('yoowii-prescript-tooltip-trigger');
        trigger.removeAttribute('aria-hidden');
        trigger.setAttribute('role', 'button');
        trigger.tabIndex = 0;
        trigger.setAttribute('aria-label', trigger.getAttribute('alt') || 'Informations complémentaires');
        if (!content.id) {
            content.id = `yoowii-prescript-tooltip-${crypto.randomUUID?.() || Math.random().toString(36).slice(2)}`;
        }
        trigger.setAttribute('aria-describedby', content.id);
        content.classList.add('yoowii-prescript-tooltip-content');
    }


    // Visual decoration only: provider inputs, handlers and their positions remain unchanged.
    decorateOptionCard(row) {
        const checkbox = row.querySelector('.class_champ_variable[type="checkbox"]');
        if (!checkbox) return;
        row.classList.add('yoowii-prescript-option-card');
        const label = row.querySelector('.web_ligne_variable_saisie_intitule label')?.textContent?.trim() || '';
        const slug = label.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        const knownOptions = new Set([
            'vernis-selectif', 'vernis-selectif-3d', 'zone-de-grattage',
            'dorure-a-chaud', 'dorure-a-chaud-3d', 'gaufrage',
            'code-barres-unitaire', 'personnalisation-unitaire', 'nfc',
        ]);
        if (knownOptions.has(slug)) row.dataset.prescriptOption = slug;
        else delete row.dataset.prescriptOption;
        this.syncOptionCard(row);
    }

    syncOptionCard(row) {
        const checkbox = row.querySelector('.class_champ_variable[type="checkbox"]');
        if (!checkbox) return;
        row.classList.toggle('is-selected', checkbox.checked);
        row.classList.toggle('is-disabled', checkbox.disabled || row.classList.contains('css_zone_grisee'));
    }

    onOptionCardClick(event) {
        const row = event.target.closest('.yoowii-prescript-option-card');
        if (!row || !this.configurationScope().contains(row)) return;
        if (event.target.closest('input, label, button, a, select, textarea, .yoowii-prescript-tooltip')) return;
        const checkbox = row.querySelector('.class_champ_variable[type="checkbox"]');
        if (!checkbox || checkbox.disabled || row.classList.contains('css_zone_grisee')) return;
        checkbox.click(); // invokes the original Prescript onclick exactly once
        this.syncOptionCard(row);
    }

    syncOptionCardStates() {
        this.configurationScope().querySelectorAll('.yoowii-prescript-option-card')
            .forEach((row) => this.syncOptionCard(row));
    }

    disableUnavailableSupplierControls(row) {
        if (!row.classList.contains('css_zone_grisee')) return;

        row.setAttribute('aria-disabled', 'true');
        row.querySelectorAll('input:not([type="hidden"]), select, textarea, button').forEach((control) => {
            control.disabled = true;
        });
    }

    assembleSupplierDocument() {
        if (this.hasOptionsTarget) {
            [...this.embedTarget.querySelectorAll('[id^="BALISE_BLOC_"]')].forEach((block) => {
                block.classList.add('yoowii-prescript-option-block');
                this.optionsTarget.append(block);
            });
        }
        if (this.hasPricingTarget) {
            const pricing = this.embedTarget.querySelector('#div_tableau_de_prix_NUMERO_EN_COURS');
            if (pricing && pricing.parentElement !== this.pricingTarget) {
                pricing.classList.add('yoowii-prescript-price-results');
                this.pricingTarget.append(pricing);
            }
        }

        // Option blocks are moved out of the supplier source before this
        // point. Scope this lookup to the product page so technical VAR_*
        // fields remain hidden after their containing block is relocated.
        this.configurationScope().querySelectorAll('[id^="VAR_"]').forEach((field) => {
            field.classList.add('yoowii-prescript-technical-field');
            field.setAttribute('aria-hidden', 'true');
            field.tabIndex = -1;
        });
        const supplierPriceButton = this.embedTarget.querySelector('#get_price_table');
        if (supplierPriceButton) {
            supplierPriceButton.classList.add('yoowii-prescript-supplier-action');
            supplierPriceButton.setAttribute('aria-hidden', 'true');
            supplierPriceButton.tabIndex = -1;
        }
        this.embedTarget.querySelector('#save_configuration')?.classList.add('yoowii-prescript-supplier-action');
        this.embedTarget.classList.add('is-prescript-assembled');
        this.refreshPurchaseAction();
        this.scheduleSummary();
    }

    scheduleGalleryUpdate() {
        window.clearTimeout(this.galleryTimer);
        this.galleryTimer = window.setTimeout(() => this.publishGallery(), 80);
    }

    // GalleryView remains an invisible supplier data source only. Yoowii owns
    // the visible carousel, its responsive layout and its accessibility.
    publishGallery() {
        const gallery = this.embedTarget.querySelector('.contenu_bloc_image_tableau');
        if (!gallery) return;

        const title = gallery.querySelector('.gv_overlay h4')?.textContent?.trim() || '';
        const thumbnails = [...gallery.querySelectorAll('.gv_thumbnail img[src]')];
        const panels = [...gallery.querySelectorAll('.gv_panel img[src]')];
        const images = [...thumbnails, ...panels].reduce((items, image) => {
            const src = this.safeImageUrl(image.getAttribute('src'));
            if (!src || items.some((item) => item.src === src)) return items;
            items.push({ src, thumbnailSrc: src, alt: image.alt?.trim() || title });

            return items;
        }, []);
        if (images.length === 0) return;

        const activeThumbnail = gallery.querySelector('.gv_thumbnail.current img[src]');
        const activeSrc = this.safeImageUrl(activeThumbnail?.getAttribute('src'));
        const activeIndex = Math.max(0, images.findIndex((image) => image.src === activeSrc));
        const signature = JSON.stringify({ images, activeIndex });
        if (signature === this.gallerySignature) return;

        this.gallerySignature = signature;
        document.dispatchEvent(new CustomEvent('yoowii:prescript-gallery-update', {
            detail: { context: this.summaryContextValue, images, activeIndex },
        }));
    }

    safeImageUrl(value) {
        if (!value) return null;
        try {
            const url = new URL(value, window.location.href);

            return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
        } catch {
            return null;
        }
    }

    onSupplierInput(event) {
        const control = event.target;
        this.syncOptionCardStates();
        if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) return;
        if (control.matches('[id^="VAR_"], #code_liaison, input[name="optionsRadios"]')) {
            this.scheduleSummary();
            this.refreshPurchaseAction();

            return;
        }

        this.configurationCode = null;
        const code = this.codeField();
        if (code) code.value = '';
        this.markQuoteStale();
        this.clearQuote();
        this.scheduleSummary();
    }

    syncConfiguration() {
        if (!this.visible) {
            return;
        }

        const field = this.codeField();
        const code = field?.value?.trim();
        if (!code || code === this.configurationCode) {
            return;
        }

        const hasPreviousConfiguration = this.configurationCode !== null;
        this.configurationCode = code;
        if (hasPreviousConfiguration) {
            this.quoteAbortController?.abort();
            this.markQuoteStale();
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

    calculateSupplierPrice(event) {
        event?.preventDefault();
        this.clearError();
        if (typeof window.checkBeforeCalculate !== 'function') {
            this.showError('Le calculateur Préscript est momentanément indisponible.');

            return;
        }
        window.checkBeforeCalculate();
        window.setTimeout(() => {
            this.assembleSupplierDocument();
            this.refreshPurchaseAction();
        }, 0);
    }

    async prepareCart(event) {
        event?.preventDefault();
        const quantity = this.quantity();
        if (!quantity || !this.selectedPriceOption()) {
            this.showError('Choisissez une quantité et un délai dans le tableau des prix.');

            return;
        }
        if (typeof window.saveConfiguration !== 'function') {
            this.showError('L’enregistrement de la configuration Préscript est indisponible.');

            return;
        }

        this.clearError();
        this.setLoading(true);
        const code = this.codeField();
        if (code) code.value = '';
        this.configurationCode = null;
        try {
            window.saveConfiguration();
            const configurationCode = await this.waitForConfigurationCode();
            this.configurationCode = configurationCode;
            await this.requestQuote(configurationCode, quantity, true);
        } catch (error) {
            this.showError(error.message || 'La configuration ne peut pas être enregistrée pour le moment.');
        } finally {
            if (!this.pending) this.setLoading(false);
        }
    }

    async requestQuote(code, quantity, addToCart = false) {
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
            this.setQuoteStale(false);
            this.errorTarget.classList.add('hidden', 'd-none');
            if (addToCart) {
                this.submitQuotedItem();
            }
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
        const scope = this.configurationScope();
        const field = scope.querySelector('[name="quantity" i], [name="quantite" i], #quantity, #quantite');
        const value = field?.value?.trim();
        if (value && /^\d+$/.test(value) && Number(value) > 0) {
            return Number(value);
        }

        // Prescript can render quantities as a price-table radio instead of a
        // named input. Its generated ids end with the selected quantity.
        const selectedPrice = scope.querySelector('input[name="optionsRadios"]:checked');
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
        this.configurationScope().querySelectorAll('.web_ligne_variable_saisie').forEach((row) => {
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
        const serializedProfile = this.element.getAttribute('data-realisaprint-prescript-quote-summary-profile-value');
        if (!serializedProfile || serializedProfile === 'null') return null;

        let profile;
        try {
            profile = JSON.parse(serializedProfile);
        } catch {
            return null;
        }

        const fields = profile?.version === 'v1' && Array.isArray(profile.fields)
            ? profile.fields
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
        const scope = this.configurationScope();
        if (field.input_id) {
            const control = scope.querySelector(`#${CSS.escape(field.input_id)}`);
            if (control) return control;
        }
        if (!field.input_name) return null;

        const controls = [...scope.querySelectorAll(`[name="${CSS.escape(field.input_name)}"]`)];
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
        const label = labelledBy ? this.configurationScope().querySelector(`#${CSS.escape(labelledBy)}`) : null;

        return label?.textContent?.trim() || '';
    }

    valueFor(control) {
        if (!control) return '';
        if (control instanceof HTMLSelectElement) {
            return control.selectedOptions[0]?.textContent?.trim() || '';
        }
        if (control.type === 'radio' || control.type === 'checkbox') {
            if (!control.checked) return '';
            const label = this.configurationScope().querySelector(`label[for="${CSS.escape(control.id)}"]`);
            return label?.textContent?.trim() || control.value?.trim() || '';
        }

        return control.value?.trim() || '';
    }

    clearQuote() {
        if (this.hasQuoteResultTarget) {
            this.quoteResultTarget.innerHTML = '<p class="text-base-content/70" data-empty-quote>Le prix final dépend de votre configuration.</p>';
        }
    }

    markQuoteStale() {
        if (this.quoteResultTarget.querySelector('[data-quote-token]')) {
            this.setQuoteStale(true);
        }
    }

    setQuoteStale(stale) {
        this.quoteStale = stale;
        if (this.hasQuoteStaleTarget) {
            this.quoteStaleTarget.classList.toggle('hidden', !stale);
        }
    }

    setLoading(loading) {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.toggle('hidden', !loading);
        }
        if (this.hasSupplierCalculateTarget) {
            this.supplierCalculateTarget.toggleAttribute('disabled', loading);
        }
        if (this.hasAddToCartTarget) {
            this.addToCartTarget.toggleAttribute('disabled', loading);
        }
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.classList.remove('hidden', 'd-none');
    }

    clearError() {
        if (!this.hasErrorTarget) return;

        this.errorTarget.textContent = '';
        this.errorTarget.classList.add('hidden', 'd-none');
    }

    productPage() {
        return this.element.closest('[data-product-experience="print"]');
    }

    configurationScope() {
        return this.productPage() || this.element;
    }

    codeField() {
        return this.configurationScope().querySelector('#code_liaison');
    }

    selectedPriceOption() {
        return this.configurationScope().querySelector('input[name="optionsRadios"]:checked');
    }

    refreshPurchaseAction() {
        if (!this.hasAddToCartTarget) return;
        const selected = this.selectedPriceOption();
        this.addToCartTarget.classList.toggle('hidden', !selected);
    }

    waitForConfigurationCode() {
        return new Promise((resolve, reject) => {
            const deadline = Date.now() + 20000;
            const timer = window.setInterval(() => {
                const code = this.codeField()?.value?.trim();
                if (code && /^\d+$/.test(code)) {
                    window.clearInterval(timer);
                    resolve(code);
                } else if (Date.now() >= deadline) {
                    window.clearInterval(timer);
                    reject(new Error('L’enregistrement de la configuration Préscript a expiré.'));
                }
            }, 150);
        });
    }

    submitQuotedItem() {
        const form = this.quoteResultTarget.querySelector('form');
        if (form instanceof HTMLFormElement) {
            form.requestSubmit();
        }
    }
}
