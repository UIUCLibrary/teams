<?php
namespace Teams\Form;

use Laminas\Form\Form;

class TeamForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'team',
            'type' => TeamFieldset::class,
        ]);
    }
}
