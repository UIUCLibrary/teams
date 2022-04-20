<?php


namespace Teams\Form;

use Laminas\Form\Form;
use Teams\Form\Element\AllSiteSelect;

class TeamSitesAddRemoveForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'teamSites',
            'type' => TeamSitesFieldset::class,
        ]);
    }
}
