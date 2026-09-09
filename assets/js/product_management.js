(() => {
    'use strict';
    const rows = document.getElementById('specRows');
    const add = document.getElementById('addSpec');
    const status = document.getElementById('specStatus');
    if (!rows || !add || !status) return;
    const update = () => {
        add.disabled = rows.children.length >= 30;
        status.textContent = `${rows.children.length} of 30 specifications. Blank rows are ignored.`;
    };
    rows.addEventListener('click', event => {
        if (!event.target.matches('.remove-spec')) return;
        event.target.closest('.spec-row').remove();
        update();
        add.focus();
    });
    add.addEventListener('click', () => {
        if (rows.children.length >= 30) return;
        const row = document.createElement('div');
        row.className = 'spec-row';
        for (const [name, label, length] of [['spec_key[]', 'Specification name', 50], ['spec_value[]', 'Specification value', 100]]) {
            const input = document.createElement('input');
            input.type = 'text'; input.name = name; input.placeholder = label;
            input.setAttribute('aria-label', label); input.maxLength = length;
            row.appendChild(input);
        }
        const remove = document.createElement('button');
        remove.type = 'button'; remove.className = 'remove-spec remove-link'; remove.textContent = 'Remove';
        row.appendChild(remove); rows.appendChild(row);
        update(); row.querySelector('input').focus();
    });
    update();
})();
