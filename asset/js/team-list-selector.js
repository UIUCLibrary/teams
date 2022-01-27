$( document ).ready(function() {
    $("button.fa.fa-minus").hover(function(){
        $(this).toggleClass("fa-plus");
        $(this).toggleClass("fa-minus");
        $(this).parents("tr").toggleClass("current-team")

    });

    $("button.fa.fa-plus").hover(function(){
        $(this).toggleClass("fa-plus");
        $(this).toggleClass("fa-minus");
    });
});