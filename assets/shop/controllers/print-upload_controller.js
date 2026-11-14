import { Controller } from '@hotwired/stimulus';

const MAX_FILE_SIZE = 100 * 1024 * 1024;
const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff'];
const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/tiff'];

export default class extends Controller {
    static targets = ['bar', 'dropzone', 'error', 'form', 'input', 'label', 'progress', 'submit'];

    connect() {
        this.uploading = false;
        this.request = null;
        this.defaultLabel = this.labelTarget.textContent;
        this.updateSubmitState();
    }

    disconnect() {
        if (this.request && this.uploading) {
            this.request.abort();
        }
    }

    dragover(event) {
        event.preventDefault();
        if (!this.uploading) {
            this.dropzoneTarget.classList.add('is-dragging');
        }
    }

    dragleave() {
        this.dropzoneTarget.classList.remove('is-dragging');
    }

    drop(event) {
        event.preventDefault();
        this.dropzoneTarget.classList.remove('is-dragging');
        if (this.uploading || !event.dataTransfer || !event.dataTransfer.files.length) {
            return;
        }

        try {
            this.inputTarget.files = event.dataTransfer.files;
        } catch (error) {
            this.showError('La sélection par glisser-déposer a échoué. Choisissez le fichier avec le bouton de sélection.');
            return;
        }
        this.selected();
    }

    selected() {
        this.clearError();
        this.resetProgress();
        const file = this.inputTarget.files[0];
        this.labelTarget.textContent = file
            ? `${file.name} · ${this.formatSize(file.size)}`
            : this.defaultLabel;

        const error = this.validateFile(file);
        if (error && file) {
            this.showError(error);
        }
        this.updateSubmitState();
    }

    validateFile(file) {
        if (!file) {
            return 'Choisissez un fichier avant de l’envoyer.';
        }
        if (file.size < 1) {
            return 'Le fichier sélectionné est vide.';
        }
        if (file.size > MAX_FILE_SIZE) {
            return 'Le fichier dépasse la taille maximale de 100 Mo.';
        }

        const extension = file.name.split('.').pop().toLowerCase();
        if (!ALLOWED_EXTENSIONS.includes(extension) ||
            (file.type && !ALLOWED_MIME_TYPES.includes(file.type))) {
            return 'Formats acceptés : PDF, JPG, PNG et TIFF.';
        }

        // The server checks the real MIME type and remains authoritative.
        return null;
    }

    updateSubmitState() {
        const file = this.inputTarget.files[0];
        this.submitTarget.disabled = this.uploading || Boolean(this.validateFile(file));
    }

    submit(event) {
        event.preventDefault();
        if (this.uploading) {
            return;
        }
        this.clearError();

        const fileError = this.validateFile(this.inputTarget.files[0]);
        if (fileError) {
            this.showError(fileError);
            this.updateSubmitState();
            return;
        }
        // Required checkbox and file constraints must be enforced with XHR too.
        if (!this.formTarget.reportValidity()) {
            return;
        }

        this.uploading = true;
        this.updateSubmitState();
        this.resetProgress();
        this.progressTarget.classList.remove('hidden', 'd-none');
        this.progressTarget.setAttribute('aria-valuenow', '0');

        const request = new XMLHttpRequest();
        this.request = request;
        request.open('POST', this.formTarget.action);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept', 'application/json');
        request.timeout = 180000;

        request.upload.addEventListener('progress', (progressEvent) => {
            if (!progressEvent.lengthComputable) {
                return;
            }
            const progress = Math.min(100, Math.round((progressEvent.loaded / progressEvent.total) * 100));
            this.barTarget.style.width = `${progress}%`;
            this.barTarget.textContent = `${progress}%`;
            this.progressTarget.setAttribute('aria-valuenow', String(progress));
        });
        request.addEventListener('load', () => {
            let payload = {};
            try {
                payload = JSON.parse(request.responseText);
            } catch (error) {
                // A server error page is not a successful JSON upload response.
            }
            if (request.status >= 200 && request.status < 300 && payload.redirect_url) {
                window.location.assign(payload.redirect_url);
                return;
            }

            const fallback = request.status === 413
                ? 'Le fichier dépasse la taille autorisée par le serveur.'
                : request.status === 403
                    ? 'La session a expiré ou l’accès est refusé. Rechargez la page.'
                    : 'Le fichier n’a pas pu être envoyé. Réessayez.';
            this.fail(payload.message || fallback);
        });
        request.addEventListener('error', () => {
            this.fail('La connexion a été interrompue pendant l’envoi. Réessayez.');
        });
        request.addEventListener('timeout', () => {
            this.fail('Le délai d’envoi est dépassé. Réessayez.');
        });
        request.addEventListener('abort', () => {
            this.fail('L’envoi a été interrompu.');
        });

        request.send(new FormData(this.formTarget));
    }

    fail(message) {
        this.uploading = false;
        this.request = null;
        this.showError(message);
        this.updateSubmitState();
    }

    resetProgress() {
        this.progressTarget.classList.add('hidden', 'd-none');
        this.progressTarget.setAttribute('aria-valuenow', '0');
        this.barTarget.style.width = '0%';
        this.barTarget.textContent = '0%';
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.classList.remove('hidden', 'd-none');
        this.progressTarget.classList.add('hidden', 'd-none');
    }

    clearError() {
        this.errorTarget.textContent = '';
        this.errorTarget.classList.add('hidden', 'd-none');
    }

    formatSize(bytes) {
        return `${(bytes / 1024 / 1024).toFixed(2).replace('.', ',')} Mo`;
    }
}
