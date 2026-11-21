export function init(config) {
    const salaryType = document.getElementById('salary_type');
    const monthlySalaryGroup = document.getElementById('monthly_salary_group');
    const perStudentGroup = document.getElementById('per_student_amount_group');
    const perStudentAmount = document.getElementById('per_student_amount');
    const updateSalaryVisibility = () => {
        monthlySalaryGroup.hidden = salaryType.value !== 'monthly';
        perStudentGroup.hidden = salaryType.value !== 'per_student';
        perStudentAmount.required = salaryType.value === 'per_student';
        perStudentAmount.disabled = salaryType.value !== 'per_student';
    };
    salaryType.addEventListener('change', updateSalaryVisibility);
    updateSalaryVisibility();
}
