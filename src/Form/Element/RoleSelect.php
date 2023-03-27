<?php


namespace Teams\Form\Element;

class RoleSelect extends TeamSelect
{
    protected $data_placeholder = 'Select Role';

    protected $data_base_url = ['resource' => 'role'];


    public function getValueOptions(): array
    {
        $valueOptions = [];

        //TODO get user id         $identity = $this->getServiceLocator()
        //            ->get('Omeka\AuthenticationService')->getIdentity(); $user_id = identity->getId();
        $em = $this->getEntityManager();
        $users = $em->getRepository('Teams\Entity\TeamRole')->findAll();
        //this is set to display the teams for the current user. This works in many contexts for
        //normal users, but not for admins doing maintenance or adding new users to a team
        foreach ($users as $user):
            $user_name = $user->getName();
        $user_id = $user->getId();
        $valueOptions[$user_id] = $user_name;
        endforeach;


        $prependValueOptions = $this->getOption('prepend_value_options');
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }
        return $valueOptions;
    }
}
