$(window).on('load', function() {
    //not ideal, but for some reason the chosen option from chosen-options.js are getting unset, so settin them here
    $("#o-modules-team-item-sets").chosen({
        allow_single_deselect: true,
        disable_search_threshold: 10,
        width: '100%',
        include_group_label_in_selected: true,
    }).change( function(event, params) {

            let $values = $("#o-modules-team-remove-item-sets").val();
            if (params.deselected){
                let $label = $("#o-modules-team-item-sets option[value='"+params.deselected+"']").text();
                $("#o-modules-team-remove-item-sets").append('<option value='+params.deselected+'>'+$label+'</option>').trigger("chosen:updated");
                $values.push(params.deselected);
                console.log($values);
            }
            if (params.selected) {
                $values.splice($.inArray(params.selected, $values), 1);
            }
            $("#o-modules-team-remove-item-sets").val($values).trigger("chosen:updated");
    });
});
