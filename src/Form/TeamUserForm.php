<?php


namespace Teams\Form;


use Zend\Form\Form;

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