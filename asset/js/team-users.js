// Adapted from omeka/omeka-s site-users.js.
// Key ordering requirement: Omeka.initializeSelector must run first so that
// the DOM rows exist before we try to set the role <select> values.
$(document).ready(function() {
    var permissionsTable = $('#site-user-permissions');
    var existingRows = permissionsTable.data('existing-rows');
    var index = 0;

    var updateRowIndex = function(rowId) {
        var rowInput = $('.resource-id[value="' + rowId + '"]');
        var row = rowInput.parents('.resource-row');
        row.find('[name*="o:team_users[__index__]"]').each(function() {
            var inputName = $(this).attr('name');
            var newinputName = inputName.replace('__index__', index);
            $(this).attr('name', newinputName);
        });
        index++;
    };

    // Bind the appendRow handler before initializing so it fires for every row,
    // including those created for pre-existing team members.
    permissionsTable.on('appendRow', function() {
        updateRowIndex($('[name="o:team_users[__index__][o:user][o:id]"]').val());
    });

    // Initialize the selector first — this creates DOM rows from existingRows
    // and triggers 'appendRow' (and therefore updateRowIndex) for each one.
    Omeka.initializeSelector('#site-user-permissions', '#user-selector');

    // Now that rows are in the DOM, restore each member's saved role selection.
    $.each(existingRows, function() {
        var selectedRole = this.role;
        var existingRow = $('.resource-id[value="' + this.id + '"]').parents('.resource-row');
        existingRow.find('select').val(selectedRole);
    });
});
