import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['stock', 'productCode', 'name'];
    static values = { baseCode: String };

    connect() {
        this.stockTarget.querySelector('select')?.addEventListener('change', () => this.updateIdentity());
        this.updateIdentity();
    }

    updateIdentity() {
        const stock = this.stockTarget.querySelector('select');
        const option = stock?.selectedOptions?.[0];
        const productCode = this.productCodeTarget.querySelector('input');
        const name = this.nameTarget.querySelector('input');
        const label = this.stockLabel(option?.textContent);

        if (!label || !productCode || !name) {
            return;
        }

        productCode.value = `${this.baseCodeValue}_${this.codePart(label)}`;
        name.value = label;
    }

    stockLabel(value) {
        return value?.split(' — ', 2)[1]?.trim() || '';
    }

    codePart(value) {
        return value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[’']/g, '')
            .replace(/[^A-Za-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .toUpperCase();
    }
}
