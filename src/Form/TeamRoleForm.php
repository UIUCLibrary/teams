<?php


namespace Teams\Form;

use Zend\Form\Form;

class TeamRoleForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'role',
            'type' => TeamRoleFieldset::class,
        ]);


        $this->add([
            'type' => 'submit',
            'name' => 'submit',
            'attributes' => [
                'value' => 'Create New Role',
            ],
        ]);
    }

}