export function init(config) {
    (function () {
        var meta = JSON.parse(config.dataset.students);
        var sel = document.getElementById('student_id');
        var name = document.getElementById('student_name');
        var level = document.getElementById('level');
        var father = document.getElementById('father_name');
        var date = document.getElementById('completion_date');
        if (!name || !level || !father || !date) return;
        function updatePreview() {
            document.getElementById('preview-name').textContent = name.value.trim() || 'Student name';
            document.getElementById('preview-father').textContent = father.value.trim() ? 'S/O ' + father.value.trim() : '';
            document.getElementById('preview-level').textContent = level.value;
            document.getElementById('preview-date').textContent = date.value || 'Not selected';
        }
        [name, father, level, date].forEach(function (input) { input.addEventListener('input', updatePreview); input.addEventListener('change', updatePreview); });
        updatePreview();
        if (!sel) return;
        sel.addEventListener('change', function () {
            var m = meta[sel.value];
            if (!m) return;
            if (name) name.value = m.name || name.value;
            if (level && m.level) level.value = m.level;
            updatePreview();
        });
    })();
}
