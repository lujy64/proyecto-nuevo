(function () {
    const money = (value) => {
        const number = Number.isFinite(value) ? value : 0;
        return '$ ' + Math.round(number).toLocaleString('es-AR');
    };

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm || 'Confirmar accion?')) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-settlement-calculator]').forEach((form) => {
        const field = (name) => form.querySelector(`[data-calc="${name}"]`);
        const output = (name) => document.querySelector(`[data-output="${name}"]`);
        const value = (name) => parseFloat(field(name)?.value || '0') || 0;

        const render = () => {
            const gross = value('gross');
            const cash = value('cash');
            const fuel = value('fuel');
            const rental = value('rental');
            const paidField = field('paid');
            const paid = paidField && paidField.value === '' ? rental : value('paid');
            const virtual = gross - cash;
            const driver = gross - fuel - rental;
            const transfer = virtual - paid;

            output('virtual').textContent = money(virtual);
            output('driver').textContent = money(driver);
            output('transfer').textContent = money(transfer);
            output('owner').textContent = money(paid);
        };

        form.querySelectorAll('input, select').forEach((input) => {
            input.addEventListener('input', render);
            input.addEventListener('change', render);
        });

        render();
    });
})();
