<?php


namespace Teams\Form\Element;

class AllItemSetSelect extends TeamSelect
{
    protected $data_placeholder = 'Select Item Sets';

    protected $data_base_url = ['resource' => 'item_set'];

    public function getValueOptions(): array
    {
        $valueOptions = [];

        //TODO get user id         $identity = $this->getServiceLocator()
        //            ->get('Omeka\AuthenticationService')->getIdentity(); $user_id = identity->getId();
        $em = $this->getEntityManager();
        $api = $this->getApiManager();
        $item_sets = $api->search('item_sets', ['bypass_team_filter'=>true])->getContent();
        //this is set to display the teams for the current user. This works in many contexts for
        //normal users, but not for admins doing maintenance or adding new users to a team
        foreach ($item_sets as $item_set):
            if ($item_set->owner()) {
                $owner = $item_set->owner()->name();
            } else {
                $owner = 'No One';
            }
        $item_set_name = $item_set->displayTitle() . ' (' . $owner . ')';
        $item_set_id = $item_set->id();
        $valueOptions[$item_set_id] = $item_set_name;
        endforeach;


        $prependValueOptions = $this->getOption('prepend_value_options');
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }
        return $valueOptions;
    }

    public function setPlaceholder($placeholder)
    {
        $this->data_placeholder = $placeholder;
    }
}
