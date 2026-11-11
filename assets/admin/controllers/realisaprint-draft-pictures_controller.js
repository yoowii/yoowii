import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['status', 'results', 'selected', 'pictureUrl', 'pictureFile'];
    static values = { url: String, token: String };

    async load() {
        const stock = this.element.querySelector('[name$="[stock]"]')?.value;
        if (!stock) {
            this.setStatus('Sélectionnez d’abord un stock Realisaprint.', 'text-danger');
            return;
        }
        this.setStatus('Chargement des images Realisaprint…', 'text-secondary');
        this.resultsTarget.replaceChildren();
        const body = new URLSearchParams({ _token: this.tokenValue, stock });
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body,
                credentials: 'same-origin',
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Les images ne peuvent pas être chargées.');
            this.render(payload.pictures || []);
        } catch (error) {
            this.setStatus(error.message || 'Les images ne peuvent pas être chargées.', 'text-danger');
        }
    }

    render(pictures) {
        if (!pictures.length) {
            this.setStatus('Aucun visuel n’a été retourné pour cette configuration.', 'text-secondary');
            return;
        }
        this.setStatus(`${pictures.length} visuel${pictures.length > 1 ? 's' : ''} disponible${pictures.length > 1 ? 's' : ''}. Choisissez-en un ou laissez vide.`, 'text-secondary');
        pictures.forEach((url, index) => {
            const column = document.createElement('div');
            column.className = 'col-sm-6 col-lg-4';
            const label = document.createElement('label');
            label.className = 'card card-link h-100 p-2';
            const image = document.createElement('img');
            image.className = 'img-fluid rounded';
            image.src = url;
            image.alt = `Visuel fournisseur ${index + 1}`;
            image.loading = 'lazy';
            const choice = document.createElement('input');
            choice.type = 'radio';
            choice.name = 'realisaprint-draft-picture-choice';
            choice.className = 'form-check-input mt-2';
            choice.value = url;
            choice.checked = this.selectedTarget.value === url;
            choice.addEventListener('change', () => {
                this.selectedTarget.value = choice.value;
                this.pictureUrlTarget.value = '';
                this.pictureFileTarget.value = '';
            });
            label.append(image, choice);
            column.append(label);
            this.resultsTarget.append(column);
        });
    }

    clearProviderChoice() {
        if (this.pictureUrlTarget.value || this.pictureFileTarget.files.length) {
            this.selectedTarget.value = '';
            this.resultsTarget.querySelectorAll('input[type="radio"]').forEach((choice) => { choice.checked = false; });
        }
    }

    setStatus(message, className) {
        this.statusTarget.textContent = message;
        this.statusTarget.className = className;
    }
}
