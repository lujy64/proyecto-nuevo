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

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const wrapper = button.closest('.password-field');
        const input = wrapper?.querySelector('[data-password-input]');

        if (!input) {
            return;
        }

        button.addEventListener('click', () => {
            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            button.textContent = isVisible ? 'Ver' : 'Ocultar';
            button.setAttribute('aria-pressed', String(!isVisible));
            button.setAttribute('aria-label', isVisible ? 'Mostrar password' : 'Ocultar password');
            input.focus();
        });
    });

    document.querySelectorAll('[data-period-type]').forEach((select) => {
        const form = select.closest('form');
        const dayField = form?.querySelector('[data-period-day]');
        const weekField = form?.querySelector('[data-period-week]');

        if (!form || !dayField || !weekField) {
            return;
        }

        const syncPeriodFields = () => {
            const isWeek = select.value === 'week';
            dayField.hidden = isWeek;
            weekField.hidden = !isWeek;
            dayField.querySelector('input')?.toggleAttribute('required', !isWeek);
            weekField.querySelector('input')?.toggleAttribute('required', isWeek);
        };

        select.addEventListener('change', syncPeriodFields);
        syncPeriodFields();
    });

    document.querySelectorAll('[data-filter-period]').forEach((select) => {
        const form = select.closest('form');
        const dayInput = form?.querySelector('[data-filter-day]');
        const monthInput = form?.querySelector('[data-filter-month]');
        const weekInput = form?.querySelector('[data-filter-week]');
        const dayField = dayInput?.closest('label');
        const monthField = monthInput?.closest('label');
        const weekField = weekInput?.closest('label');

        if (!form || (!dayField && !monthField && !weekField)) {
            return;
        }

        const syncFilterFields = () => {
            const value = select.value;

            if (dayField) {
                dayField.hidden = value !== 'day';
            }

            if (monthField) {
                monthField.hidden = value !== 'month';
            }

            if (weekField) {
                weekField.hidden = value !== 'week';
            }
        };

        select.addEventListener('change', syncFilterFields);
        syncFilterFields();
    });

    document.querySelectorAll('[data-entry-calculator]').forEach((form) => {
        const field = (name) => form.querySelector(`[data-calc="${name}"]`);
        const output = (name) => document.querySelector(`[data-output="${name}"]`);
        const value = (name) => parseFloat(field(name)?.value || '0') || 0;

        const render = () => {
            const gross = value('gross');
            const deductions =
                value('app_expenses') +
                value('cash') +
                value('rental');
            const fuel = value('fuel');
            const net = gross - deductions;

            if (output('deductions')) {
                output('deductions').textContent = money(deductions);
            }

            if (output('fuel')) {
                output('fuel').textContent = money(fuel);
            }

            if (output('net')) {
                output('net').textContent = money(net);
                output('net').classList.toggle('text-danger', net < 0);
                output('net').classList.toggle('text-gain', net >= 0);
            }
        };

        form.querySelectorAll('input, select, textarea').forEach((input) => {
            input.addEventListener('input', render);
            input.addEventListener('change', render);
        });

        render();
    });
})();
