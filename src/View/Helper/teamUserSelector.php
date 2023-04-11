<?php

namespace Teams\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class teamUserSelector extends AbstractHelper
{
    /**
     * Return the user selector form control.
     *
     * @param string $title The title of the selector
     * @param bool $alwaysOpen Whether the selector is always open
     * @return string
     */
    public function __invoke($title = null, $alwaysOpen = true, $bypassTeams = false)
    {
        $users = $this->getView()->api()->search('users', ['sort_by' => 'name', 'bypass_team_filter' => $bypassTeams])->getContent();

        $usersByInitial = [];
        foreach ($users as $user) {
            $initial = strtoupper($user->name())[0];
            $usersByInitial[$initial][] = $user;
        }

        return $this->getView()->partial(
            'common/user-selector',
            [
                'users' => $users,
                'usersByInitial' => $usersByInitial,
                'title' => $title,
                'alwaysOpen' => $alwaysOpen,
            ]
        );
    }

}