/*
Create or destroy role element after a selector event
Dynamically creates and adds role fields to the form for each team added

Update default sites based on team selection

If no default team has been selected, then upon selecting a team, use that team as default
*/
window.addEventListener("load", function () {

    //first, hide the role select template element
    $(".hidden_no_value").parent().parent().css("visibility","hidden");

    //disable the options for default so that the user cant select a default team where user isn't a memeber
    $("#default_team option").attr('disabled','disabled');

    // if user checks the box, update default sites based on currently selected default
    $("#update_default_sites").click(function() {
        if($(this).is(":checked")) {
            updateDefaultSites();

        }
    });

    //as teams are added and removed, manage the form elements that depend on the team to get populated
    // including role elements, options for default teams, and sites
    $('#team').on('change', function(evt, params) {

        if (params.selected){
            let id = params.selected;

            let team_name = $(`#team option[value=${id}]`).text();

            //enable team to be selected as the default team options
            $(`#default_team option[value=${id}]`).removeAttr('disabled').trigger("chosen:updated");


            makeRoleElement($(`#team option[value=${id}]`).text(),params.selected);

            //if no default is currently selected, use this team as default
            if ($('#default_team').val()===null){

                $('#default_team').val(id).trigger("chosen:updated");
            }

        } else{
            let id = params.deselected;

            $(`#role_el_for_${id}`).remove();

            //if they removed the team which was selected as default, remove default team and find next available
            if ($('#default_team').val() == params.deselected){

                //remove default
                $('#default_team').val([]).trigger("chosen:updated");

                //if there are other options, pick the first one for new default
                if ($('#team').val().length){
                    let default_team_id =$('#team').val()[0];
                    $('#default_team').val(default_team_id).trigger("chosen:updated");
                }
            }
            $(`#default_team option[value=${id}]`).attr('disabled', 'disabled').trigger("chosen:updated");
        }
        if($("#update_default_sites").is(":checked")) {
            updateDefaultSites();
        }
    });

    $("#default_team").on('change',function () {
        if($("#update_default_sites").is(":checked")) {
            updateDefaultSites();
        }

    })


});

/*
As of Omeka 3.x, items can "belong" to sites, and users have default sites that their items get assigned to. This updates
those default sites so they match the team a user is assigned to
 */
function updateDefaultSites() {
    //get all of the currently selected team ids
    let $default_team_id = $('#default_team').chosen().val();

    //get all of the currently selected team names
    let $default_team_name = $('#team').find('option[value="'+$default_team_id+'"]').text();

    //clear all of the sites
    $('#default_sites').chosen().val([]).trigger('chosen:updated');

    //update sites based on team selection
    selectSites($default_team_name);
}

function selectSites($label) {

    //get all of the sites for the label
    let $group = $.map( $('#default_sites optgroup[label="'+$label+'"] option'), function( n ) { return n.value; });

    $('#default_sites').val($group).trigger('chosen:updated');

}

//generate role field
function makeRoleElement(team_name, team_id, role = 1){


    let fieldDiv = document.createElement("div");
    fieldDiv.className = "field";
    fieldDiv.id = `role_el_for_${team_id}`;

    let fieldMeta = document.createElement("div");
    fieldMeta.className = "field-meta";

    fieldDiv.appendChild(fieldMeta);

    let label = document.createElement('label');
    label.setAttribute('for', `${team_name} role`);
    let user_name = document.getElementById('name').value;

    //also update the options for default team
    if (team_name ==="~~Add New Team~~"){
        team_name = '';
        if (user_name !== ''){
            team_name = `${user_name}'s Team`;
            label.innerText = `${team_name} (new team) Role`;
            $("#default_team").find('option[value="-1"]').text(team_name).trigger("chosen:updated")

        } else{
            team_name = 'New Team';
            label.innerText = `${team_name} Role`
        }
        // $('#default_team').append(`<option value="-1">${team_name}</option>`).trigger('chosen:updated');
    } else {
        label.innerText = team_name + ' Role';

    }

    fieldMeta.appendChild(label);

    let inputsDiv = document.createElement("div");
    inputsDiv.className = "inputs";

    let select = document.createElement('select');
    select.setAttribute('name', `user-information[o-module-teams:TeamRole][${team_id}]`);
    select.setAttribute('data-placeholder', 'Select Role');
    select.className = "chosen-select";
    select.id = `role_for_${team_name}`;


    for (let [role_id, role_name] of Object.entries(role_array)){
        let option = document.createElement('option');
        option.value = role_id;
        option.innerText = role_name;
        if (role_id == role){
            option.selected = true;
        } else {
            if (!$('#team').attr('data-mutable'))
                option.disabled = true;
        }
        select.appendChild(option);
    }
    inputsDiv.appendChild(select);

    fieldDiv.appendChild(inputsDiv);

    let teams = document.getElementById('team');
    let teams_container =teams.parentElement.parentElement;

    teams_container.parentNode.insertBefore(fieldDiv, teams_container.nextSibling);

}



//populate role for each of the user's pre-existing teams (for edit views)
//MOVED: in order to populate with the correct role, moved this to the partial: teams/partial/user/edit.phtml
// window.addEventListener("load",function () {
//     let user_teams = $("select#team").children("option:selected");
//
//     for (let i = 0; i < user_teams.length; i++){
//         let team_name = user_teams[i].innerText;
//         let team_id = user_teams[i].value;
//         makeRoleElement(team_name, team_id, 3);
//     }
// });