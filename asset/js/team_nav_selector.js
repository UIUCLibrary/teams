window.addEventListener("load", function () {
    let team = document.createElement('p');
    let viewTeam = document.createElement('a');
    let editTeam = document.createElement('a');
    let editTeamSR = document.createElement('span')

    team.setAttribute('class','user-id');
    viewTeam.setAttribute('class','o-icon-users button user-show');
    viewTeam.setAttribute('href', editTeamHref);
    viewTeam.innerText = teamName;
    editTeam.setAttribute('class', 'user-settings button')
    editTeamSR.setAttribute('class', 'sr-only')
    editTeamSR.innerText = "Edit Team"

    team.setAttribute('id', 'nav_team');
    viewTeam.setAttribute('id', 'team-link');

    editTeam.appendChild(editTeamSR)
    team.appendChild(viewTeam);
    team.appendChild(editTeam)

    document.getElementById("user").appendChild(team);
    let newButton = document.createElement('p');
    newButton.className = 'logout';
    let buttonAnchor = document.createElement('a');
    buttonAnchor.setAttribute('href', switchTeamHref);
    buttonAnchor.innerText = 'Switch';

    newButton.appendChild(buttonAnchor);

    $(newButton).insertAfter( "#nav_team" );}, false );