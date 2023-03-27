<?php


namespace Teams\Form\Element;

class AllSiteSelect extends TeamSelect
{
    protected $data_placeholder = 'Select Site(s)';

    protected $data_base_url = ['resource' => 'sites'];

    protected $valueOptions = [];


    public function getValueOptions(): array
    {
        if ($this->valueOptions) {
            return $this->valueOptions;
        } else {
            $valueOptions = [];
            //TODO get user id         $identity = $this->getServiceLocator()
            //            ->get('Omeka\AuthenticationService')->getIdentity(); $user_id = identity->getId();
            $em = $this->getEntityManager();
            $api = $this->getApiManager();
            $sites = $api->search('sites', ['bypass_team_filter'=>true])->getContent();
            //this is set to display the teams for the current user. This works in many contexts for
            //normal users, but not for admins doing maintenance or adding new users to a team
            foreach ($sites as $site):
                if ($site->owner()) {
                    $owner = $site->owner()->name();
                } else {
                    $owner = 'No One';
                }
            $site_name = $site->title() . ' (' . $owner . ')';
            $site_id = $site->id();
            $valueOptions[$site_id] = $site_name;
            endforeach;


            $prependValueOptions = $this->getOption('prepend_value_options');
            if (is_array($prependValueOptions)) {
                $valueOptions = $prependValueOptions + $valueOptions;
            }
            return $valueOptions;
        }
    }

    public function setPlaceholder($placeholder)
    {
        $this->data_placeholder = $placeholder;
    }

    public function setValueOptions(array $options)
    {
        $this->valueOptions = $options;
    }
}
