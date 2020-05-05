<?php
namespace Teams\Form;

use Zend\Form\Form;

class TeamForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'team',
            'type' => TeamFieldset::class,
        ]);

//        $this->add([
//            'type' => 'submit',
//            'name' => 'submit',
//            'attributes' => [
//                'value' => 'Create New Team',
//            ],
//        ]);
    }
}
