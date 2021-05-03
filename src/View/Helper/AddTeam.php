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
        $response = $this->getView()->api()->search('team', ['sort_by' => 'name'])->getContent();
        $user_id = $this->getView()->identity()->getId();
        $teams = array();
        foreach ($response as $team):

            foreach ($team->users() as $user):
                if ($user->getUser()->getId() == $user_id){
                    $teams[] = $team;
                }

            endforeach;
        endforeach;
//        $teams = $response->getContent();
        return $this->getView()->partial(
            'teams/partial/add-team',
            [
                'teams' => $teams,
                'totalTeamCount' => count($teams),
            ]
        );
    }
}
