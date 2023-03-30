//if adding a user to a team:
//      .add
//      disable option

//if removing a user from a team
//      if it has the .existing class
//          add the .remove class
//          turn the trashcan into an undo button
//      else
//          delete the entry from the list
//          enable the option from the select

$('#team-users').on('click', '.o-icon-delete.existing', function(event) {
    event.preventDefault();

    var removeLink = $(this);
    var teamRow = $(this).closest('tr');
    var teamInput = removeLink.closest('tr').find('input');
    teamInput.attr('name', 'remove_team[]')

    // Undo remove team link.
    var undoRemoveLink = $('<a>', {
        href: '#',
        class: 'fa fa-undo',
        title: Omeka.jsTranslate('Undo remove team'),
        click: function(event) {
            event.preventDefault();
            teamInput.attr('name', 'existing_team[]');
            teamRow.toggleClass('delete');
            removeLink.show();
            $(this).remove();
        },
    });

    teamRow.toggleClass('delete');
    undoRemoveLink.insertAfter(removeLink);
    removeLink.hide();
});
$('#team-users').on('click', '.o-icon-delete.add', function(event) {
    event.preventDefault();

    var removeLink = $(this);
    var teamRow = $(this).closest('tr');
    var teamInput = removeLink.closest('tr').find('input');
    teamInput.prop('disabled', true);


    // Undo remove team link.
    var undoRemoveLink = $('<a>', {
        href: '#',
        class: 'fa fa-undo',
        title: Omeka.jsTranslate('Undo remove team'),
        click: function(event) {
            event.preventDefault();
            teamInput.prop('disabled', false);
            teamRow.toggleClass('delete');
            removeLink.show();
            $(this).remove();
        },
    });

    teamRow.toggleClass('delete');
    undoRemoveLink.insertAfter(removeLink);
    removeLink.hide();
});