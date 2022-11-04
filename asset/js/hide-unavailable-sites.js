$(document).ready(function() {

    //remove any empty cells and any trash icons because items are associated with sites via teams
    $('table#item-sites tr td span.tablesaw-cell-content').each(function(){ // For each element
        if( $(this).text().trim() === '' ) {
            $(this).closest('td').remove(); // if it is empty, it removes it
        }
    });

    //remove the side site selector interface
    $('div#site-selector').empty()
    $('div#site-selector').append('<h3>Site-Item relationships are Managed by Teams</h3>')

});
