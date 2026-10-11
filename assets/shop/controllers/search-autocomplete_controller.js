import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'panel', 'products', 'suggestions', 'status'];
    static values = { url: String };

    connect() { this.abortController = null; this.timer = null; }
    disconnect() { window.clearTimeout(this.timer); this.abortController?.abort(); }

    search() {
        window.clearTimeout(this.timer);
        const query = this.inputTarget.value.trim();
        if (query.length < 2) return this.close();
        this.timer = window.setTimeout(() => this.load(query), 200);
    }

    async load(query) {
        this.abortController?.abort();
        this.abortController = new AbortController();
        this.panelTarget.hidden = false;
        this.statusTarget.textContent = 'Recherche en cours…';
        try {
            const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`, { signal: this.abortController.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Search request failed');
            this.render(await response.json());
        } catch (error) {
            if (error.name !== 'AbortError') this.statusTarget.textContent = 'La recherche est indisponible.';
        }
    }

    render(data) {
        this.productsTarget.replaceChildren(...data.products.map((product) => this.link(product.name, product.url)));
        this.suggestionsTarget.replaceChildren(...data.suggestions.map((suggestion) => this.button(suggestion)));
        this.statusTarget.textContent = data.products.length ? '' : 'Aucun produit trouvé.';
    }

    link(label, url) { const link = document.createElement('a'); link.className = 'block rounded-btn px-3 py-2 font-medium hover:bg-base-200'; link.href = url; link.textContent = label; return link; }
    button(label) { const button = document.createElement('button'); button.className = 'block w-full rounded-btn px-3 py-2 text-left hover:bg-base-200'; button.type = 'button'; button.textContent = label; button.addEventListener('click', () => { this.inputTarget.value = label; this.search(); }); return button; }
    close() { this.panelTarget.hidden = true; this.statusTarget.textContent = ''; }
    closeOnEscape(event) { if (event.key === 'Escape') { this.close(); this.inputTarget.focus(); } }
}
