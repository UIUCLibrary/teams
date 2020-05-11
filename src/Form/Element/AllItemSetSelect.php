<?php


namespace Teams\Form\Element;


class AllItemSetSelect extends TeamSelect

{
    protected $data_placeholder = 'Select ItemSets';

    protected $data_base_url = ['resource' => 'item_set'];

    public function getValueOptions()
    {


        $valueOptions = [];

        //TODO get user id         $identity = $this->getServiceLocator()
        //            ->get('Omeka\AuthenticationService')->getIdentity(); $user_id = identity->getId();
        $em = $this->getEntityManager();
        $api = $this->getApiManager();
        $users = $api->search('item_sets', ['bypass_team_filter'=>true])->getContent();
        //this is set to display the teams for the current user. This works in many contexts for
        //normal users, but not for admins doing maintenance or adding new users to a team
        foreach ($users as $user):
            $user_name = $user->displayTitle() . ' (' . $user->owner()->name() . ')';
            $user_id = $user->id();
            $valueOptions[$user_id] = $user_name;
        endforeach;


        $prependValueOptions = $this->getOption('prepend_value_options');
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }
        return $valueOptions;
    }

}