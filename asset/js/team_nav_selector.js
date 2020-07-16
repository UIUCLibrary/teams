window.addEventListener("load", function () {
    let newP = document.createElement('p');
    let newAnchor = document.createElement('a');

    newAnchor.setAttribute('href', teamHref);
    newAnchor.innerText = teamName;

    newP.innerText = 'Current Team';
    newP.setAttribute('class','user-id');
    newP.setAttribute('id', 'nav_team');
    newAnchor.setAttribute('id', 'team-link');

    newP.appendChild(newAnchor);

    document.getElementById("user").appendChild(newP);
    let newButton = document.createElement('p');
    newButton.className = 'logout';
    let buttonAnchor = document.createElement('a');
    buttonAnchor.setAttribute('href', switchTeamHref);
    buttonAnchor.innerText = 'Switch';

    newButton.appendChild(buttonAnchor);

    $(newButton).insertAfter( "#nav_team" );}, false );