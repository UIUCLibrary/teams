let teams_array = [];

$("#team").chosen().change(function () {
    teams_array = $(this).val();
    // makeRoleElement($(this).val(), $(this).val());
    alert('clicked');
    /* get array of ids in the roles section
     *
     * Account for removed teams-
     * for each id in the roles section not in teams_array: delete the role form element
     *
     * Account for added teams-
     * for each number in teams_array not in roles section: add role form element
     *
     * Function to remove role form element
     * $(#team_id).remove()
     *
     * function to add role element
     *
     */

});

$('#team').on('change', function(evt, params) {
    console.log(params.selected);
    if (params.selected){
        let id = params.selected;
        makeRoleElement($(`#team option[value=${id}]`).text(),params.selected);


        document.getElementById('team').f
    }else{
        $(`#role_el_for_${params.deselected}`).remove();

    }

    // can now use params.selected and params.deselected
});

$('#team').on('change', function(evt, params) {
    console.log(params.deselected);
    //delete the deselected
    $(`role_el_for${params.deselected}`).remove();
    // can now use params.selected and params.deselected
});


function makeRoleElement(team_name, team_id){


    let fieldDiv = document.createElement("div");
    fieldDiv.className = "field";
    fieldDiv.id = `role_el_for_${team_id}`;

    let fieldMeta = document.createElement("div");
    fieldMeta.className = "field-meta";

    fieldDiv.appendChild(fieldMeta);

    let label = document.createElement('label');
    label.setAttribute('for', `${team_name} role`);
    label.innerText = team_name + ' Role';

    fieldMeta.appendChild(label);

    let inputsDiv = document.createElement("div");
    inputsDiv.className = "inputs";

    let select = document.createElement('select');
    select.setAttribute('name', `user-information[o-module-teams:TeamRole[${team_id}]`);
    select.setAttribute('data-placeholder', 'Select Role');
    select.className = "chosen-select";
    select.id = `role_for_${team_name}`;


    for (let [role_id, role_name] of Object.entries(role_array)){
        let option = document.createElement('option');
        option.value = role_id;
        option.innerText = role_name;
        select.appendChild(option);
    }
    inputsDiv.appendChild(select);

    fieldDiv.appendChild(inputsDiv);

    let teams = document.getElementById('team');
    let teams_container =teams.parentElement.parentElement;

    teams_container.parentNode.insertBefore(fieldDiv, teams_container.nextSibling);


}


