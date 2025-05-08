window.addEventListener("load", function () {
    $("#o-modules-team-item-sets").chosen().change( function(event, params) {
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
