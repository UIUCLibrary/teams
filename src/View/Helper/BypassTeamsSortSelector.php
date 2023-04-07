<?php

namespace Teams\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class BypassTeamsSortSelector extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'teams/common/sort-selector-bypass-teams';


    /**
     * Modified from core SortSelector View Helper
     *
     * @param string|null $partialName Name of view script, or a view model
     * @return string
     */
    public function __invoke(string $partialName = null)
    {
        $partialName = $partialName ?: self::PARTIAL_NAME;

        $view = $this->getView();
        $params = $view->params();
        $bypassTeams = $params->fromQuery('bypass_team_filter');

        $args = [
            'bypassTeams' => $bypassTeams,
        ];

        return $view->partial($partialName, $args);
    }

}