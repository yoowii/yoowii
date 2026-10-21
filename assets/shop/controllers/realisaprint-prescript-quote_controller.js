import { Controller } from '@hotwired/stimulus';

// The supplier script sets input#code_liaison programmatically without emitting
// an event. The bridge observes it without changing supplier markup, fields or
// handlers. It never uses that observation to calculate a price automatically.
export default class extends Controller {
    static values = { quoteUrl: String, cartUrl: String, csrfToken: String, summaryContext: String };
    static targets = ['addToCart', 'error', 'embed', 'loading', 'options', 'pricing', 'purchaseSummary', 'quoteResult', 'quoteStale', 'status', 'supplierCalculate'];

    connect() {
        this.configurationCode = null;
        this.cartRequestKey = null;
        this.pending = false;
        this.quoteAbortController = null;
        this.quoteSequence = 0;
        this.quoteStale = false;
        this.visible = false;
        this.timer = null;
        this.summaryTimer = null;
        this.galleryTimer = null;
        this.gallerySignature = null;
        this.priceNeedsRefresh = false;
        this.supplierPriceCalculated = false;
        this.supplierCalculation = null;
        this.onEmbedInput = this.scheduleSummary.bind(this);
        this.onSupplierInput = this.onSupplierInput.bind(this);
        this.onOptionCardClick = this.onOptionCardClick.bind(this);
        this.onSupplierControlHover = this.onSupplierControlHover.bind(this);
        this.onSupplierPricePopoverHover = this.onSupplierPricePopoverHover.bind(this);
        this.observeVisibility();
        if (this.hasEmbedTarget) {
            this.observeEmbed();
            this.syncEmbedState();
        }
        this.configurationScope().addEventListener('change', this.onSupplierInput, true);
        this.configurationScope().addEventListener('click', this.onOptionCardClick);
        this.configurationScope().addEventListener('mouseover', this.onSupplierControlHover, true);
        this.configurationScope().addEventListener('mouseover', this.onSupplierPricePopoverHover, true);
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
        this.configurationScope().removeEventListener('mouseover', this.onSupplierControlHover, true);
        this.configurationScope().removeEventListener('mouseover', this.onSupplierPricePopoverHover, true);
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
            this.pricingObserver = new MutationObserver(() => {
                this.assembleSupplierDocument();
                this.confirmSupplierCalculation();
                this.refreshPurchaseAction();
            });
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
            this.decorateSupplierPriceTable(table);
        });
        this.embedTarget.querySelectorAll('.web_ligne_variable_saisie').forEach((row) => {
            this.decorateSupplierTooltip(row);
            this.disableSupplierControlTooltips(row);
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
        const hasImage = [...content.querySelectorAll('img')].some((image) => image.getAttribute('src')?.trim());
        if (!hasImage && this.visibleText(content) === '') {
            tooltip.hidden = true;
            tooltip.setAttribute('aria-hidden', 'true');
            trigger.tabIndex = -1;

            return;
        }

        tooltip.classList.remove('hidden');
        tooltip.hidden = false;
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
        const isCheckboxOption = row.querySelector('input[type="checkbox"]') !== null;
        const title = row.querySelector('.web_ligne_variable_saisie_intitule');
        if (!isCheckboxOption && title && tooltip.parentElement !== title) {
            title.after(tooltip);
            row.classList.add('yoowii-prescript-field--label-tooltip');
            tooltip.classList.add('yoowii-prescript-tooltip--label');
        }
        if (!content.querySelector('.yoowii-prescript-tooltip-details')) {
            const details = document.createElement('div');
            details.className = 'yoowii-prescript-tooltip-details';
            [...content.childNodes].forEach((node) => {
                if (!(node instanceof HTMLImageElement)) details.append(node);
            });
            content.append(details);
        }
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
        const thumbnails = {
            'vernis-selectif': 'vernis_selectif.png',
            'vernis-selectif-3d': 'vernis_selectif_3d.png',
            'zone-de-grattage': 'zone_de_grattage.png',
            'dorure-a-chaud': 'dorure_a_chaud.png',
            'dorure-a-chaud-3d': 'dorure_a_chaud_3d.png',
            gaufrage: 'gaufrage.png',
            'code-barres-unitaire': 'code_barres_unitaire.png',
            'personnalisation-unitaire': 'personnalisation_unitaire.png',
            nfc: 'nfc.png',
        };
        if (knownOptions.has(slug)) {
            row.dataset.prescriptOption = slug;
            row.style.setProperty('--yoowii-option-thumb', `url("/themes/yoowii-theme/media/prescript-options/${thumbnails[slug]}")`);
        } else {
            delete row.dataset.prescriptOption;
            row.style.removeProperty('--yoowii-option-thumb');
        }
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

    // Mark delivery groups from the supplier's actual headers, rather than
    // relying on a fixed column order. The local CSS can then distinguish
    // Urgence, Express and Standard consistently.
    decorateSupplierPriceTable(table) {
        const deliveriesByColumn = new Map();
        const occupiedColumns = [];
        const rows = [...table.querySelectorAll('tr')];
        // Headers can be split over several rows and use both colspan and
        // rowspan. Build their actual column positions before decorating price
        // cells instead of assuming Urgence/Express/Standard are fixed.
        for (let rowIndex = 0; rowIndex < rows.length; rowIndex += 1) {
            const row = rows[rowIndex];
            if (row.querySelector('input[name="optionsRadios"]')) break;
            let column = 0;
            [...row.children].forEach((cell) => {
                while (occupiedColumns[column] > rowIndex) column += 1;
                const colspan = Number.parseInt(cell.getAttribute('colspan') || '1', 10);
                const rowspan = Number.parseInt(cell.getAttribute('rowspan') || '1', 10);
                const delivery = this.deliveryKey(cell.textContent);
                if (delivery) {
                    cell.dataset.yoowiiDelivery = delivery;
                    for (let offset = 0; offset < colspan; offset += 1) deliveriesByColumn.set(column + offset, delivery);
                }
                for (let offset = 0; offset < colspan; offset += 1) occupiedColumns[column + offset] = rowIndex + rowspan;
                column += colspan;
            });
        }
        if (deliveriesByColumn.size === 0) return;

        table.querySelectorAll('tr').forEach((row) => {
            let cellColumn = 0;
            [...row.children].forEach((cell) => {
                const colspan = Number.parseInt(cell.getAttribute('colspan') || '1', 10);
                const delivery = deliveriesByColumn.get(cellColumn);
                if (delivery) cell.dataset.yoowiiDelivery = delivery;
                cellColumn += colspan;
            });
        });
        this.decorateSupplierPricePopovers(table);
    }

    decorateSupplierPricePopovers(table) {
        table.querySelectorAll('.infobulle_particuliere[data-content]').forEach((trigger) => {
            if (trigger.dataset.yoowiiPricePopoverBound === 'true') return;
            trigger.dataset.yoowiiPricePopoverBound = 'true';
            const position = () => window.setTimeout(() => this.positionSupplierPricePopover(trigger), 0);
            trigger.addEventListener('mouseenter', position);
            trigger.addEventListener('focus', position);
        });
    }

    positionSupplierPricePopover(trigger) {
        const describedBy = trigger.getAttribute('aria-describedby');
        const popovers = [...document.querySelectorAll('.popover')];
        const popover = describedBy ? document.getElementById(describedBy) : popovers.at(-1);
        if (!popover) return;

        popover.classList.add('yoowii-prescript-price-popover');
        const triggerBox = trigger.getBoundingClientRect();
        const popoverBox = popover.getBoundingClientRect();
        const horizontal = Math.min(Math.max(12, triggerBox.right - popoverBox.width), window.innerWidth - popoverBox.width - 12);
        const above = triggerBox.top - popoverBox.height - 10;
        const vertical = above >= 12 ? above : Math.min(triggerBox.bottom + 10, window.innerHeight - popoverBox.height - 12);
        popover.style.setProperty('position', 'fixed', 'important');
        popover.style.setProperty('inset', 'auto', 'important');
        popover.style.setProperty('left', `${horizontal}px`, 'important');
        popover.style.setProperty('top', `${vertical}px`, 'important');
        popover.style.setProperty('z-index', '60', 'important');
    }

    onSupplierPricePopoverHover(event) {
        const trigger = event.target.closest?.('.infobulle_particuliere[data-content]');
        if (!trigger || !this.configurationScope().contains(trigger)) return;

        // Prescript can rebuild the table after the initial decorators ran.
        // Delay until its Bootstrap handler has inserted the popover in <body>.
        window.setTimeout(() => this.positionSupplierPricePopover(trigger), 20);
    }

    deliveryKey(value) {
        const label = value?.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase() || '';
        if (label.includes('urgence')) return 'urgence';
        if (label.includes('express')) return 'express';
        if (label.includes('standard')) return 'standard';

        return null;
    }

    // Prescript attaches Bootstrap/jQuery Popovers directly to selects with a
    // hover trigger. The supplier CSS is intentionally absent, so those
    // popovers are both visually inconsistent and redundant with our icon.
    disableSupplierControlTooltips(row) {
        row.querySelectorAll('select, textarea, input:not([type="hidden"])').forEach((control) => {
            this.disableSupplierControlTooltip(control);
        });
    }

    onSupplierControlHover(event) {
        const control = event.target.closest?.('select, textarea, input:not([type="hidden"])');
        if (control && this.configurationScope().contains(control)) this.disableSupplierControlTooltip(control);
    }

    disableSupplierControlTooltip(control) {
        control.removeAttribute('title');
        control.removeAttribute('data-original-title');
        const jquery = window.jQuery;
        if (!jquery?.fn?.popover) return;
        try {
            jquery(control).popover('destroy');
        } catch {
            // A supplier page can initialize a different Popover API.
        }
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
        if (control.matches('input[name="optionsRadios"]')) {
            this.refreshPurchaseAction();
            this.scheduleSummary();
            return;
        }
        if (control.matches('[id^="VAR_"], #code_liaison')) {
            this.scheduleSummary();
            this.refreshPurchaseAction();
            return;
        }

        this.priceNeedsRefresh = true;
        this.cartRequestKey = null;
        this.supplierPriceCalculated = false;
        this.configurationCode = null;
        const code = this.codeField();
        if (code) code.value = '';
        this.markQuoteStale();
        this.clearQuote();
        this.refreshPurchaseAction();
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
        // A pre-existing matrix can be stale. Record it before invoking the
        // supplier so that merely calling checkBeforeCalculate() never marks
        // the old table as valid.
        this.supplierCalculation = {
            fingerprint: this.priceTableFingerprint(),
            table: this.supplierPriceTable(),
            deadline: Date.now() + 15000,
        };
        window.checkBeforeCalculate();
        this.waitForSupplierCalculation();
    }

    // Single storefront request: the backend saves, validates the quote and inserts the cart item.
    async prepareCart(event) {
        event?.preventDefault();
        if (this.pending) return;
        if (this.priceNeedsRefresh) {
            this.showError('Votre configuration a changé. Calculez les prix avant de continuer.');
            return;
        }
        const radio = this.selectedPriceOption();
        const quantity = this.priceQuantity(radio);
        const delivery = radio ? this.priceDelivery(radio) : null;
        if (!quantity || !['standard', 'urgence', 'express'].includes(delivery)) {
            this.showError('Choisissez une quantité et un délai valides dans le tableau.');
            return;
        }
        // Match Prescript's own saveConfiguration() collection contract.
        // Calculations must update the supplier's hidden values before reading them.
        if (typeof window.calculer !== 'function' || window.calculer('sans_graphisme') !== 0) {
            this.showError('La configuration fournisseur est invalide. Vérifiez les options sélectionnées.');
            return;
        }
        const shown = window.shown_vars;
        const quantityVariableNumber = window.qty_var_num;
        const delayVariableNumber = window.delay_var_num;
        if (!shown || typeof shown !== 'object' || quantityVariableNumber == null || delayVariableNumber == null) {
            this.showError('Les variables du formulaire Prescript ne sont pas disponibles.');
            return;
        }
        const shownNames = new Set(Object.keys(shown));
        const quantityVariable = `VARTICLE_${quantityVariableNumber}_`;
        const delayVariable = `VARTICLE_${delayVariableNumber}_`;
        const variables = {};
        // The supplier template uses a per-document prefix before the variable id.
        const supplierFields = this.configurationScope().querySelectorAll('input[name^="VARTICLE_"], select[name^="VARTICLE_"], textarea[name^="VARTICLE_"]');
        supplierFields.forEach((field) => {
            const match = field.name?.match(/^VARTICLE_(?:NUMERO_EN_COURS|\\d+)_(\\d+_)$/);
            if (!match || field.value === '') return;
            const name = `VARTICLE_${match[1]}`;
            if ((name !== quantityVariable && shownNames.has(name)) || name === delayVariable) {
                variables[name] = field.value;
            }
        });
        // Some Prescript price tables hold the selected delay outside the
        // serialized configuration controls. For the audited product only,
        // recover its known variable from the selected supplier tariff radio.
        // Do not generalize these Realisaprint-specific codes to other products.
        if (!Object.hasOwn(variables, delayVariable)
            && this.embedTarget?.dataset.prescriptProductCode === 'PRINT_CARTE_DE_VISITE_CALENDRIER'
            && delayVariable === 'VARTICLE_23025_') {
            variables[delayVariable] = String({ standard: 0, urgence: 1, express: 2 }[delivery]);
        }
        if (Object.keys(variables).length === 0 || !Object.hasOwn(variables, delayVariable)) {
            this.showError('Impossible de récupérer les paramètres Prescript pour cette sélection.');
            return;
        }
        this.pending = true;
        this.clearError();
        this.setLoading(true);
        try {
            const response = await fetch(this.cartUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    quantity, delivery, variables,
                    idempotency_key: (this.cartRequestKey ||= crypto.randomUUID()),
                    _token: this.csrfTokenValue,
                }),
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Impossible d’ajouter le produit au panier.');
            }
            window.location.assign(payload.redirect_url);
        } catch (error) {
            this.showError(error.message || 'Impossible d’ajouter le produit au panier.');
        } finally {
            this.pending = false;
            this.setLoading(false);
        }
    }

    async requestQuote(code, quantity, addToCart = false) {
        if (this.pending) return;
        const delivery = this.priceDelivery(this.selectedPriceOption());
        if (!['standard', 'urgence', 'express'].includes(delivery)) {
            this.showError('Sélectionnez un tarif et un délai valides dans le tableau.');
            return;
        }
        const abortController = new AbortController();
        this.quoteAbortController = abortController;
        const sequence = ++this.quoteSequence;
        this.pending = true;
        this.setLoading(true);
        try {
            const response = await fetch(this.quoteUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ code, quantity, delivery, _token: this.csrfTokenValue }),
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
        const selectedPriceQuantity = this.priceQuantity(this.selectedPriceOption());
        if (selectedPriceQuantity) return selectedPriceQuantity;

        const field = scope.querySelector('[name="quantity" i], [name="quantite" i], #quantity, #quantite');
        const value = field?.value?.trim();
        if (value && /^\d+$/.test(value) && Number(value) > 0) {
            return Number(value);
        }
        return null;
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
        const explicitLabel = this.visibleText(explicit);
        if (explicitLabel) return explicitLabel;
        const labelledBy = control?.getAttribute('aria-labelledby');
        const label = labelledBy ? this.configurationScope().querySelector(`#${CSS.escape(labelledBy)}`) : null;

        return this.visibleText(label);
    }

    // Prescript sometimes appends its Popover initializer as a <script> in the
    // same node as the label. Keep the script for the provider, but never use
    // executable source as customer-facing configuration text.
    visibleText(element) {
        if (!element) return '';
        const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, {
            acceptNode: (node) => node.parentElement?.closest('script, style')
                ? NodeFilter.FILTER_REJECT
                : NodeFilter.FILTER_ACCEPT,
        });
        const text = [];
        while (walker.nextNode()) text.push(walker.currentNode.textContent);

        return text.join(' ').replace(/\s+/g, ' ').trim();
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

    // Display-only adapters read Prescript's live radio table; no price is
    // invented or independently submitted by Yoowii.
    priceQuantity(radio) {
        const row = radio.closest('tr');
        if (!row) return null;
        const quantityCell = [...row.children].find((cell) => {
            const text = cell.textContent?.replace(/[\s\u00a0]/g, '') || '';
            return /^\d+(?:exemplaires?)?$/i.test(text);
        });
        const text = quantityCell?.textContent || '';
        const normalized = text.replace(/[\s\u00a0]/g, '');
        const match = normalized.match(/^(\d+)(?:exemplaires?)?$/i);
        return match ? Number(match[1]) : null;
    }

    priceDelivery(radio) {
        const cell = radio.closest('td, th');
        const classes = [cell?.className, cell?.parentElement?.className].join(' ');
        return cell?.dataset.yoowiiDelivery || this.deliveryKey(classes);
    }

    priceCells(radio, delivery) {
        const row = radio.closest('tr');
        if (!row) return [];
        const cells = [...row.querySelectorAll(':scope > td, :scope > th')];
        const deliveryCells = cells.filter((cell) => {
            const classes = [cell.className, row.className].join(' ');
            return (cell.dataset.yoowiiDelivery || this.deliveryKey(classes)) === delivery;
        });

        // A Prescript delivery choice is commonly rendered as three sibling
        // cells: radio, price, then delay. Its headers may be rebuilt after a
        // calculation, so retain this structural fallback while never reading
        // values from a different delivery group.
        if (deliveryCells.length > 0) return deliveryCells;
        const radioCell = radio.closest('td, th');
        const index = cells.indexOf(radioCell);

        return index === -1 ? [] : cells.slice(index, index + 3);
    }

    priceMatrix() {
        if (!this.hasPricingTarget) return new Map();
        const result = new Map();
        this.pricingTarget.querySelectorAll('input[name="optionsRadios"]').forEach((radio) => {
            const quantity = this.priceQuantity(radio);
            const delivery = this.priceDelivery(radio);
            const cell = radio.closest('td');
            if (!quantity || !delivery || !cell || radio.disabled) return;
            if (!result.has(quantity)) result.set(quantity, new Map());
            const cells = this.priceCells(radio, delivery);
            const raw = cells.map((priceCell) => priceCell.textContent.replace(/\s+/g, ' ').trim()).join(' ');
            const details = this.priceDetails(cells);
            const priceSource = `${this.priceCell(cells)?.textContent?.replace(/\s+/g, ' ').trim() || ''} ${details.text}`;
            const euro = priceSource.match(/(?:\d[\d\s.,]*)\s*€(?:\s*HT)?/i);
            // Preserve the supplier's French monetary representation. The
            // summary is a view of the selected table cell, never a quote.
            const price = euro?.[0]?.trim() || '';
            const delay = priceSource.match(/J\s*\+\s*\d+/i)?.[0]?.replace(/\s+/g, '') || '';
            result.get(quantity).set(delivery, { radio, price, delay, ...details });
        });
        return result;
    }

    // Prescript keeps delivery facts in its popover attribute. Read that
    // supplier-provided text directly; nothing in the summary is calculated
    // or guessed by Yoowii.
    priceCell(cells) {
        return cells.find((cell) => cell.classList.contains('cell-price')) || null;
    }

    priceDetails(cells) {
        const priceCell = this.priceCell(cells);
        const trigger = (priceCell || cells[0])?.querySelector('.infobulle_particuliere[data-content]')
            || cells.flatMap((cell) => [...cell.querySelectorAll('.infobulle_particuliere[data-content]')])[0];
        const source = trigger?.getAttribute('data-content') || '';
        const documentFragment = new DOMParser().parseFromString(source, 'text/html');
        const text = this.visibleText(documentFragment.body);
        const deliveryDate = text.match(/Livraison\s+pr[ée]vue\s*(?:le)?\s*:?\s*([^\n]+?)(?=\s*(?:Poids|Prix|Livraison|$))/i)?.[1]?.trim() || '';
        const weight = text.match(/Poids\s*:\s*([\d.,]+\s*kg)/i)?.[1]?.trim() || '';
        const shippingIncluded = text.match(/Livraison\s+comprise\s+dans\s+le\s+prix/i)?.[0] || '';

        return { text, deliveryDate, weight, shippingIncluded };
    }

    refreshPurchaseAction() {
        const matrix = this.priceMatrix();
        const selected = this.selectedPriceOption();
        const valid = Boolean(selected && !this.priceNeedsRefresh);
        if (this.hasSupplierCalculateTarget) {
            this.supplierCalculateTarget.classList.toggle('hidden', this.supplierPriceCalculated && !this.priceNeedsRefresh);
        }
        if (this.hasAddToCartTarget) {
            this.addToCartTarget.classList.toggle('hidden', !selected);
            this.addToCartTarget.disabled = !valid || this.pending;
        }
        if (this.hasQuoteStaleTarget) {
            this.quoteStaleTarget.classList.toggle('hidden', !this.priceNeedsRefresh);
        }
        if (this.hasPurchaseSummaryTarget) {
            const quantity = valid ? this.priceQuantity(selected) : null;
            const delivery = valid ? this.priceDelivery(selected) : null;
            const info = quantity && delivery ? matrix.get(quantity)?.get(delivery) : null;
            if (!valid || !info) {
                this.purchaseSummaryTarget.hidden = true;
            } else {
                this.purchaseSummaryTarget.hidden = false;
                this.purchaseSummaryTarget.querySelector('[data-purchase-quantity]').textContent = quantity.toLocaleString('fr-FR');
                this.purchaseSummaryTarget.querySelector('[data-purchase-delivery]').textContent = ({ urgence: 'Urgence', express: 'Express', standard: 'Standard' })[delivery] || delivery;
                this.setPurchaseDetail('delay', info.delay);
                this.setPurchaseDetail('delivery-date', info.deliveryDate);
                this.setPurchaseDetail('weight', info.weight);
                const shipping = this.purchaseSummaryTarget.querySelector('[data-purchase-shipping-included]');
                shipping.textContent = info.shippingIncluded;
                shipping.hidden = !info.shippingIncluded;
                this.purchaseSummaryTarget.querySelector('[data-purchase-price]').textContent = info.price || '—';
            }
        }
    }

    setPurchaseDetail(name, value) {
        const field = this.purchaseSummaryTarget.querySelector(`[data-purchase-${name}]`);
        const label = this.purchaseSummaryTarget.querySelector(`[data-purchase-${name}-label]`);
        if (!field) return;
        field.textContent = value || '—';
        field.hidden = !value;
        if (label) label.hidden = !value;
    }

    priceTableFingerprint() {
        if (!this.hasPricingTarget) return '';
        return [...this.pricingTarget.querySelectorAll('input[name="optionsRadios"]')]
            .map((radio) => `${radio.id}|${radio.value}|${radio.closest('td')?.textContent?.replace(/\s+/g, ' ').trim() || ''}`)
            .join('\n');
    }

    supplierPriceTable() {
        return this.hasPricingTarget ? this.pricingTarget.querySelector('.tableau-prix') : null;
    }

    waitForSupplierCalculation() {
        window.setTimeout(() => this.confirmSupplierCalculation(), 100);
    }

    confirmSupplierCalculation() {
        if (!this.supplierCalculation) return;
        this.assembleSupplierDocument();
        const fingerprint = this.priceTableFingerprint();
        if (this.priceMatrix().size > 0 && (fingerprint !== this.supplierCalculation.fingerprint || this.supplierPriceTable() !== this.supplierCalculation.table)) {
            this.supplierPriceCalculated = true;
            this.priceNeedsRefresh = false;
            this.supplierCalculation = null;
            this.clearError();
            this.refreshPurchaseAction();
            return;
        }
        if (Date.now() < this.supplierCalculation.deadline) {
            window.setTimeout(() => this.confirmSupplierCalculation(), 150);
            return;
        }
        this.supplierCalculation = null;
        this.showError('Le tableau de prix n’a pas pu être actualisé. Veuillez vérifier votre configuration puis réessayer.');
        this.refreshPurchaseAction();
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
