<?php


namespace Teams\Form;

use Laminas\Form\Form;

class TeamUserForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'team',
            'type' => TeamUserFieldset::class,
        ]);
    }
}
