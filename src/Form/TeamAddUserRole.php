<?php


namespace Teams\Form;

use Laminas\Form\Form;

class TeamAddUserRole extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'user and role',
            'type' => TeamAddUserRoleFieldset::class,
        ]);


        $this->add([
            'name' => 'add',
            'type' => 'button',
            'options' => [
                'label' => 'add'
            ]

        ]);
    }
}
