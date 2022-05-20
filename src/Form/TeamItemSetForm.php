<?php


namespace Teams\Form;

use Laminas\Form\Form;

class TeamItemSetForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'itemset',
            'type' => 'fieldset',
        ]);
        $this->add([
            'name' => 'site',
            'type' => 'fieldset',
        ]);

        $this->get('itemset')->add([
            'name' => 'itemset',
            'type' => TeamItemSetFieldset::class,
        ]);

        $this->get('site')->add([
            'name' => 'site',
            'type' => AddSiteToTeamFieldset::class,
        ]);
    }
}
