export function init(config) {
    (function () {
        const form = document.getElementById('accountCreateForm');
        const role = document.getElementById('role');
        const payType = document.getElementById('salary_type');
        if (!form || !role) return;
        const has = (el, attr, value) => (el.getAttribute(attr) || '').split(' ').includes(value);
        function sync() {
            const current = role.value;
            // Hide and disable other roles' sections so they are neither shown nor submitted.
            form.querySelectorAll('[data-role-section]').forEach(section => {
                const show = has(section, 'data-role-section', current);
                section.hidden = !show;
                section.querySelectorAll('input, select, textarea').forEach(input => { input.disabled = !show; });
            });
            const perStudent = current === 'teacher' && payType.value === 'per_student';
            form.querySelectorAll('[data-pay-field]').forEach(field => {
                const show = field.dataset.payField === 'monthly' ? !perStudent : perStudent;
                const inRole = !field.hasAttribute('data-role-section') || has(field, 'data-role-section', current);
                field.hidden = !(show && inRole);
                field.querySelectorAll('input').forEach(input => { input.disabled = field.hidden; });
            });
            document.getElementById('email').required = current !== 'staff';
            document.getElementById('designation').required = current === 'staff';
            document.getElementById('phone').required = ['teacher', 'staff'].includes(current);
            document.getElementById('monthly_salary').required = current === 'staff';
            document.getElementById('per_student_amount').required = perStudent;
            form.querySelectorAll('[data-required-for]').forEach(label => {
                label.classList.toggle('required', has(label, 'data-required-for', current));
            });
        }
        role.addEventListener('change', sync);
        payType.addEventListener('change', sync);
        sync();
    })();
}
