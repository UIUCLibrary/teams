<?php


namespace Teams\Form;


use Teams\Form\Element\AllItemSetSelect;
use Teams\Form\Element\UserSelect;
use Zend\Form\Fieldset;

class TeamItemSetFieldset extends Fieldset
{

    public function init()
    {


        $this->add([
            'name' => 'o:itemset',
            'type' => AllItemSetSelect::class,
            'options' => [
                'label' => 'ItemSet', // @translate
                'chosen' => true,
            ],
            'attributes' => [
                'multiple' => true,
            ],

        ]);

        $this->add([
            'name' => 'o:user',
            'type' => UserSelect::class,
            'options' => [
                'label' => 'Users', // @translate
                'chosen' => true,
            ],
            'attributes' => [
                'multiple' => true,
            ],

        ]);







    }


}