<?php


namespace Teams\Form;

use Laminas\Form\Form;

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