<?php
namespace Teams\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class AddTeam extends AbstractHelper
{
    /**
     * Return the team selector form control.
     *
     * @return string
     */
    public function __invoke()
    {
        $user_id = $this->getView()->identity()->getId();
        $response = $this->getView()->api()->search('team', ['team_user' => $user_id]);
        $teams = $response->getContent();
        return $this->getView()->partial(
            'teams/partial/add-team',
            [
                'teams' => $teams,
                'totalTeamCount' => count($teams),
            ]
        );
    }
}
