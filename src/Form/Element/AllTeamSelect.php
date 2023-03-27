<?php


namespace Teams\Form\Element;

class AllTeamSelect extends TeamSelect
{
    public function getValueOptions(): array
    {
        $valueOptions = [];

        //TODO get user id         $identity = $this->getServiceLocator()
        //            ->get('Omeka\AuthenticationService')->getIdentity(); $user_id = identity->getId();
        $em = $this->getEntityManager();
        $teams = $em->getRepository('Teams\Entity\Team')->findAll();
        //this is set to display the teams for the current user. This works in many contexts for
        //normal users, but not for admins doing maintenance or adding new users to a team
        $valueOptions[-1] = '~~Add New Team~~';
        foreach ($teams as $team):
            $team_name = $team->getName();
        $team_id = $team->getId();
        $valueOptions[$team_id] = $team_name;
        endforeach;

        $prependValueOptions = $this->getOption('prepend_value_options');
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }
        return $valueOptions;
    }
}
