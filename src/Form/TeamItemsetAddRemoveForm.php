<?php


namespace Teams\Form;


use Zend\Form\Form;

class TeamItemsetAddRemoveForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'addCollections',
            'type' => TeamItemSetFieldset::class,
        ]);

        $this->add([
            'name' => 'rmCollections',
            'type' => TeamItemSetFieldset::class,
        ]);

    }

}