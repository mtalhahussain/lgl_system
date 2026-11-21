export function init(config) {
    (() => {
        const selects = [...document.querySelectorAll('[data-package-batch]')];
        const output = document.getElementById('package-allocation');
        if (!output) return;
        const price = document.getElementById('price');
        const count = document.getElementById('installments');
        const plans = [...document.querySelectorAll('input[name=plan]')];
        const currency = config.dataset.currency;
        const format = minor => currency + ' ' + (minor / 100).toLocaleString('en', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const plan = () => plans.find(input => input.checked)?.value || 'full';
        function togglePlan() {
            document.querySelectorAll('[data-plan]').forEach(block => {
                const on = block.dataset.plan === plan();
                block.hidden = ! on;
                block.querySelectorAll('input, select').forEach(field => { field.disabled = ! on; });
            });
            document.getElementById('method').required = plan() === 'full';
            count.required = plan() === 'installments';
        }
        function update() {
            const chosen = selects.filter(select => select.value).map(select => ({level: select.dataset.level, fee: Number(select.selectedOptions[0].dataset.fee)}));
            if (chosen.length < 2) { output.textContent = 'Select at least two levels to preview the split.'; return; }
            const gross = chosen.reduce((sum, row) => sum + row.fee, 0);
            const total = Math.round(Number(price.value || 0) * 100);
            if (! total) { output.textContent = 'Normal fees total ' + format(gross) + '. Enter the package price.'; return; }
            if (total > gross) { output.textContent = 'Price is more than the normal fees total (' + format(gross) + ').'; return; }
            let used = 0;
            const parts = chosen.map((row, index) => {
                const share = index === chosen.length - 1 ? total - used
                    : Number((BigInt(total) * BigInt(row.fee) + BigInt(Math.floor(gross / 2))) / BigInt(gross));
                used += share;
                const each = plan() === 'installments' && Number(count.value) >= 2 ? ' (' + count.value + ' × ~' + format(Math.floor(share / Number(count.value))) + ')' : '';
                return row.level + ': ' + format(share) + each;
            });
            const discount = gross - total;
            output.textContent = parts.join(' · ') + '. Normal ' + format(gross) + ', package ' + format(total)
                + ', discount ' + format(discount) + ' (' + (discount * 100 / gross).toFixed(2) + '%).';
        }
        selects.forEach(select => select.addEventListener('change', update));
        [price, count].forEach(input => input.addEventListener('input', update));
        plans.forEach(input => input.addEventListener('change', () => { togglePlan(); update(); }));
        togglePlan();
        update();
    })();
}
