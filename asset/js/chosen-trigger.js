//create or destroy role element after a selector event
window.addEventListener("load", function () {
    $('#team').on('change', function(evt, params) {
        if (params.selected){
            let id = params.selected;
            makeRoleElement($(`#team option[value=${id}]`).text(),params.selected);


        }else{
            $(`#role_el_for_${params.deselected}`).remove();

        }

        // can now use params.selected and params.deselected
    });
});


//generate role field
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
    select.setAttribute('name', `user-information[o-module-teams:TeamRole][${team_id}]`);
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

//populate role for each of the user's pre-existing teams (for edit views)
window.addEventListener("load",function () {
    let user_teams = $("select#team").children("option:selected");

    for (let i = 0; i < user_teams.length; i++){
        let team_name = user_teams[i].innerText;
        let team_id = user_teams[i].value;
        makeRoleElement(team_name, team_id);
    }
});