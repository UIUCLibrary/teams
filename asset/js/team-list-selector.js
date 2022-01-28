$( document ).ready(function() {

    //change button on hover to indicate action
    $("button.fa.fa-minus").hover(function(){
        $(this).toggleClass("fa-plus");
        $(this).toggleClass("fa-minus");
        $(this).parents("tr").toggleClass("current-team")
    });

    //change team on click using the submit form at the top of the list
    $( "button.team-select" ).click(function() {
        $('select').val($(this).attr('data-team-id'));
        $('form#change_team').submit();
        //visual indication that something is happening while the page reloads
        $('select#team_options').css("background-color", "lightgrey").css("font-weight", "bold");
        $(this).parents('tr').siblings('.current-team').toggleClass("current-team")
    });
});