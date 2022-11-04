$(document).ready(function() {

    /* Team a resource. */

// Add the selected team to the edit panel.
    $('#team-selector .selector-child').click(function(event) {
        event.preventDefault();

        //if it was empty, this removes the empty class
        $('#team-resources').removeClass('empty');
        var teamId = $(this).data('team-internal-id');
        var teamName = $(this).data('child-search');

        if ($('#team-resources').find("input[value='" + teamId + "']").length) {
            return;
        }

        var row = $($('#team-template').data('template'));
        row.children('td.team-name').text(teamName);
        row.find('td > input').val(teamId);
        $('#team-resources > tbody:last').append(row);
    });

// Remove a team from the edit panel.
    $('#team-resources').on('click', '.o-icon-delete.existing', function(event) {
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
    $('#team-resources').on('click', '.o-icon-delete.new', function(event) {
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

    /* Update teams. */

// Update the name of a team.
    $('.teams .o-icon-edit.contenteditable')
        .on('click', function(e) {
            e.preventDefault();
            var field = $(this).closest('td').find('.team-name');
            field.focus();
        });

// Update the name of a team.
    $('.teams .team-name[contenteditable=true]')
        .focus(function() {
            var field = $(this);
            field.data('original-text', field.text());
        })
        .blur(function(e) {
            var field = $(this);
            var oldText = field.data('original-text');
            var newText = $.trim(field.text().replace(/\s+/g,' '));
            $.removeData(field, 'original-text');
            if (newText.length > 0 && newText !== oldText) {
                var url = field.data('update-url');
                $.post({
                    url: url,
                    data: {text: newText},
                    beforeSend: function() {
                        field.text(newText);
                        field.addClass('o-icon-transmit');
                    }
                })
                    .done(function(data) {
                        var row = field.closest('tr');
                        field.text(data.content.text);
                        field.data('update-url', data.content.urls.update);
                        row.find('[name="resource_ids[]"]').val(data.content.escaped);
                        row.find('.o-icon-delete').data('sidebar-content-url', data.content.urls.delete_confirm);
                        row.find('.o-icon-more').data('sidebar-content-url', data.content.urls.show_details);
                    })
                    .fail(function(jqXHR, textStatus) {
                        var msg = jqXHR.hasOwnProperty('responseJSON')
                        && typeof jqXHR.responseJSON.error !== 'undefined'
                            ? jqXHR.responseJSON.error
                            : Omeka.jsTranslate('Something went wrong');
                        alert(msg);
                        field.text(oldText);
                    })
                    .always(function () {
                        field.removeClass('o-icon-transmit');
                        field.parent().focus();
                    });
            } else {
                field.text(oldText);
            }
        })
        .keydown(function(e) {
            if (e.keyCode === 13) {
                e.preventDefault();
            }
        })
        .keyup(function(e) {
            if (e.keyCode === 13) {
                $(this).blur();
            } else if (e.keyCode === 27) {
                var field = $(this);
                var oldText = field.data('original-text');
                $.removeData(field, 'original-text');
                field.text(oldText);
                field.parent().focus();
            }
        });

});
