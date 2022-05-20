<?php


namespace Teams\Form;

use Laminas\Form\Fieldset;
use Teams\Form\Element\AllSiteSelect;

class TeamSitesFieldset extends Fieldset
{
    public function init()
    {
        $this->add([
            'name' => 'o:site',
            'type' => AllSiteSelect::class,
            'options' => [
                'label' => 'Sites', // @translate
                'chosen' => true,
                'info' => 'The sites that should belong to this team'
            ],
            'attributes' => [
                'multiple' => true,
            ],

        ]);
    }
}
