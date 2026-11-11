import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['bar', 'dropzone', 'error', 'form', 'input', 'label', 'progress', 'submit'];

    connect() {
        this.submitTarget.disabled = true;
    }

    dragover(event) {
        event.preventDefault();
        this.dropzoneTarget.classList.add('is-dragging');
    }

    dragleave() {
        this.dropzoneTarget.classList.remove('is-dragging');
    }

    drop(event) {
        event.preventDefault();
        this.dropzoneTarget.classList.remove('is-dragging');
        if (event.dataTransfer.files.length > 0) {
            this.inputTarget.files = event.dataTransfer.files;
            this.selected();
        }
    }

    selected() {
        const file = this.inputTarget.files[0];
        this.submitTarget.disabled = !file;
        if (file) {
            this.labelTarget.textContent = `${file.name} · ${this.formatSize(file.size)}`;
        }
    }

    submit(event) {
        event.preventDefault();
        this.clearError();
        this.submitTarget.disabled = true;
        this.progressTarget.classList.remove('d-none');

        const request = new XMLHttpRequest();
        request.open('POST', this.formTarget.action);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept', 'application/json');
        request.upload.addEventListener('progress', (progressEvent) => {
            if (!progressEvent.lengthComputable) return;
            const progress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
            this.barTarget.style.width = `${progress}%`;
            this.barTarget.textContent = `${progress}%`;
        });
        request.addEventListener('load', () => {
            let payload = {};
            try { payload = JSON.parse(request.responseText); } catch (error) { payload = {}; }
            if (request.status >= 200 && request.status < 300 && payload.redirect_url) {
                window.location.assign(payload.redirect_url);
                return;
            }
            this.showError(payload.message || 'Le fichier n’a pas pu être envoyé.');
            this.submitTarget.disabled = false;
        });
        request.addEventListener('error', () => {
            this.showError('La connexion a été interrompue pendant l’envoi.');
            this.submitTarget.disabled = false;
        });
        request.send(new FormData(this.formTarget));
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.classList.remove('d-none');
        this.progressTarget.classList.add('d-none');
    }

    clearError() {
        this.errorTarget.textContent = '';
        this.errorTarget.classList.add('d-none');
    }

    formatSize(bytes) {
        return `${(bytes / 1024 / 1024).toFixed(2).replace('.', ',')} Mo`;
    }
}
