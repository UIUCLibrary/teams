$(window).on('load', function() {
    // Initialize chosen on the item-sets and resource-templates multiselects.
    // Chosen handles deselection natively: deselected values are removed from
    // the select element, so they are omitted from the submitted form data and
    // the adapter treats them as removed.
    $("#o-modules-team-item-sets").chosen({
        allow_single_deselect: true,
        disable_search_threshold: 10,
        width: '100%',
        include_group_label_in_selected: true,
    });

    $("#o-modules-team-resource-templates").chosen({
        allow_single_deselect: true,
        disable_search_threshold: 10,
        width: '100%',
        include_group_label_in_selected: true,
    });
});
