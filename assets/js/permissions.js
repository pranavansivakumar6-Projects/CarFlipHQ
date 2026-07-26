(function () {
    var preset = document.querySelector('[data-permission-preset]');
    if (!preset) {
        return;
    }
    var roleSelect = document.querySelector('[data-role-select]');

    var permissionSets = {
        japan: ['can_view_imports'],
        japan_finance: ['can_view_imports', 'can_view_import_finance'],
        full_no_numbers: [
            'can_view_data',
            'can_manage_cars',
            'can_manage_tasks',
            'can_manage_sales',
            'can_import_export',
            'can_use_ai',
            'can_view_imports'
        ],
        full: [
            'can_view_data',
            'can_view_finance',
            'can_manage_cars',
            'can_manage_finance',
            'can_manage_tasks',
            'can_manage_sales',
            'can_import_export',
            'can_use_ai',
            'can_view_imports',
            'can_manage_imports',
            'can_view_import_finance'
        ]
    };

    function applyPreset(value) {
        var selected = permissionSets[value];
        if (!selected) {
            return;
        }

        document.querySelectorAll('input[name="permissions[]"]').forEach(function (input) {
            input.checked = selected.indexOf(input.value) !== -1;
        });
    }

    preset.addEventListener('change', function () {
        applyPreset(preset.value);
    });

    if (roleSelect) {
        roleSelect.addEventListener('change', function () {
            preset.value = roleSelect.value === 'admin' ? 'full' : 'japan';
            applyPreset(preset.value);
        });
    }

    if (preset.dataset.defaultPreset) {
        preset.value = preset.dataset.defaultPreset;
        applyPreset(preset.value);
    }
})();
