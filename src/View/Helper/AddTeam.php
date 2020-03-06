<?php
namespace Teams\View\Helper;

use Zend\View\Helper\AbstractHelper;

class AddTeam extends AbstractHelper
{
    /**
     * Return the group selector form control.
     *
     * @return string
     */
    public function __invoke()
    {
        $response = $this->getView()->api()->search('team', ['sort_by' => 'name']);
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
