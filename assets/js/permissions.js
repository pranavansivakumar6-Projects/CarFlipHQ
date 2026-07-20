(function () {
    var preset = document.querySelector('[data-permission-preset]');
    if (!preset) {
        return;
    }

    var permissionSets = {
        no_access: [],
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

    preset.addEventListener('change', function () {
        var selected = permissionSets[preset.value];
        if (!selected) {
            return;
        }

        document.querySelectorAll('input[name="permissions[]"]').forEach(function (input) {
            input.checked = selected.indexOf(input.value) !== -1;
        });
    });
})();
