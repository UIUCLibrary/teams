//create or destroy role element after a selector event
window.addEventListener("load", function () {

    //disable the options for default
    $("#default_team option").attr('disabled','disabled');



    // if user checks the box, update default sites based on currently selected default
    $("#update_default_sites").click(function() {
        if($(this).is(":checked")) {
            updateDefaultSites();
        }
    });

    //update default sites upon adding/removing teams if #update_default_sites box is checked
    $('#team').on('change', function(evt, params) {
        let id = params.selected;

        if (params.selected){
            let team_name = $(`#team option[value=${id}]`).text();
            //enable team to be selected as the default team options
            $(`#default_team option[value=${id}]`).removeAttr('disabled').trigger("chosen:updated");
            makeRoleElement($(`#team option[value=${id}]`).text(),params.selected);

        } else{
            $(`#role_el_for_${params.deselected}`).remove();
            $(`#default_team option[value=${id}]`).attr('disabled', 'disabled').trigger("chosen:updated");
        }
    });
});

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
    if (team_name ==="~~Add New Team~~"){
        if (user_name !== ''){
            label.innerText = `${user_name}'s Team (new team) Role`
        } else{
            label.innerText = 'New Team Role';
        }
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