<?php


namespace Teams\Form;

use Teams\Form\Element\AllItemSetSelect;
use Teams\Form\Element\AllSiteSelect;
use Teams\Form\Element\RoleSelect;
use Teams\Form\Element\UserSelect;
use Laminas\Form\Fieldset;

class AddSiteToTeamFieldset extends Fieldset
{
    public function init()
    {
        $this->add([
            'name' => 'o:site',
            'type' => AllSiteSelect::class,
            'options' => [
                'label' => 'Site', // @translate
                'chosen' => true,
            ],
            'attributes' => [
                'multiple' => true,
            ],

        ]);
    }
}
