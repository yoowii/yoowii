import { Controller } from '@hotwired/stimulus';

// The supplier script sets input#code_liaison programmatically without emitting
// an event. This controller observes that value without changing the supplier
// markup, field names or event handlers.
export default class extends Controller {
    static values = { quoteUrl: String, csrfToken: String };
    static targets = ['quoteResult', 'error', 'embed', 'status'];

    connect() {
        this.lastCode = null;
        this.pending = false;
        this.visible = false;
        this.timer = null;
        this.observeVisibility();
        if (this.hasEmbedTarget) {
            this.observeEmbed();
            this.syncEmbedState();
        }
    }

    disconnect() {
        this.stopPolling();
        this.intersectionObserver?.disconnect();
        this.mutationObserver?.disconnect();
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
                this.captureCode();

                return;
            }

            this.stopPolling();
        }, { threshold: 0.01 });
        this.intersectionObserver.observe(this.element);
    }

    observeEmbed() {
        this.mutationObserver = new MutationObserver(() => this.syncEmbedState());
        this.mutationObserver.observe(this.embedTarget, { childList: true, subtree: true });
    }

    startPolling() {
        if (this.timer !== null) {
            return;
        }

        this.timer = window.setInterval(() => this.captureCode(), 350);
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

        if (isReady && this.hasStatusTarget) {
            this.statusTarget.classList.add('hidden');
        }
    }

    async captureCode() {
        if (!this.visible || this.pending) {
            return;
        }

        const field = this.element.querySelector('#code_liaison');
        const code = field?.value?.trim();
        if (!code || code === this.lastCode) {
            return;
        }
        const quantity = this.quantity();
        if (!quantity) {
            this.showError('La quantité Préscript sélectionnée est introuvable.');
            return;
        }
        this.pending = true;
        this.lastCode = code;
        try {
            const response = await fetch(this.quoteUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ code, quantity, _token: this.csrfTokenValue }),
            });
            const payload = await response.json();
            if (!response.ok || !payload.quote_html) {
                throw new Error(payload.message || 'Le prix Préscript ne peut pas être calculé.');
            }
            this.quoteResultTarget.innerHTML = payload.quote_html;
            this.errorTarget.classList.add('hidden', 'd-none');
        } catch (error) {
            this.showError(error.message || 'Le prix Préscript ne peut pas être calculé.');
        } finally {
            this.pending = false;
        }
    }

    quantity() {
        const field = this.element.querySelector('[name="quantity"], [name="quantite"], #quantity, #quantite');
        const value = field?.value?.trim();
        return value && /^\d+$/.test(value) && Number(value) > 0 ? Number(value) : null;
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.classList.remove('hidden', 'd-none');
    }
}
