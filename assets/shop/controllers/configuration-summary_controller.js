import { Controller } from '@hotwired/stimulus';

// This component deliberately receives a normalized state event instead of
// reading a supplier DOM. Native, matrix and Prescript configurators can then
// share the exact same summary UI.
export default class extends Controller {
    static targets = ['dialog', 'empty', 'list', 'progress'];
    static values = { context: String };

    connect() {
        this.onConfigurationUpdate = this.onConfigurationUpdate.bind(this);
        document.addEventListener('yoowii:configuration-update', this.onConfigurationUpdate);
    }

    disconnect() {
        document.removeEventListener('yoowii:configuration-update', this.onConfigurationUpdate);
    }

    onConfigurationUpdate(event) {
        const { context, steps } = event.detail || {};
        if (context !== this.contextValue || !Array.isArray(steps)) {
            return;
        }

        this.render(steps);
    }

    reset() {
        document.dispatchEvent(new CustomEvent('yoowii:configuration-reset', {
            detail: { context: this.contextValue },
        }));
    }

    open() {
        if (this.hasDialogTarget && !this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }
    }

    close() {
        if (this.hasDialogTarget && this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    render(steps) {
        const completed = steps.filter((step) => step.required && step.complete).length;
        const required = steps.filter((step) => step.required).length;
        this.progressTargets.forEach((target) => {
            target.textContent = `${completed} choix sur ${required} effectués`;
        });
        this.listTargets.forEach((target) => {
            target.replaceChildren(...steps.map((step) => this.item(step)));
        });
        this.emptyTargets.forEach((target) => target.classList.toggle('hidden', steps.length > 0));
    }

    item(step) {
        const item = document.createElement('li');
        item.className = step.complete ? 'is-complete' : 'is-pending';

        const check = document.createElement('span');
        check.className = 'yoowii-print-summary-check';
        check.setAttribute('aria-hidden', 'true');
        check.textContent = '✓';

        const name = document.createElement('span');
        name.className = 'yoowii-print-summary-name';
        name.textContent = step.label;

        const value = document.createElement('strong');
        value.textContent = step.value || 'À choisir';

        const edit = document.createElement('a');
        edit.className = 'btn btn-ghost btn-xs';
        edit.href = `#yoowii-print-step-${step.code}`;
        edit.textContent = 'Modifier';
        edit.addEventListener('click', () => {
            this.close();
            document.dispatchEvent(new CustomEvent('yoowii:configuration-edit', {
                detail: { context: this.contextValue, code: step.code },
            }));
        });

        item.append(check, name, value, edit);

        return item;
    }
}
