export function initBatchName() {
    const name = document.querySelector('[data-auto-batch-number]');
    const start = document.getElementById('start_date');
    if (!name || !start) return;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const update = () => {
        const date = /^(\d{4})-(\d{2})-\d{2}$/.exec(start.value);
        if (!date || !months[Number(date[2]) - 1]) return;
        name.value = `BTC-${name.dataset.autoBatchNumber.padStart(3, '0')}-${months[Number(date[2]) - 1]}-${date[1].slice(-2)}`;
    };
    start.addEventListener('input', update);
    start.addEventListener('change', update);
    update();
}
