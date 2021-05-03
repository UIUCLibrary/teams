<?php
namespace Teams\Form;

use Laminas\Form\Form;
use Laminas\Form\Element;


class TeamUpdateForm extends Form
{
    public function init()
    {
        $this->setAttribute('id', 'team-update-form');

        $this->add([
            'type' => 'hidden',
            'name' => 'id',

        ]);

        $this->add([
            //extremely important  that this match what is in the API adapter: Teams\Api\Representation getJsonLd()
            'name' => 'o:name',
            'type' => 'Text',
            'options' => [
                'label' => 'Name', // @translate
            ],
            'attributes' => [
                'id' => 'name',
                'required' => true,
            ],
        ]);

        $this->add([

            //extremely important  that this match what is in the API adapter: Teams\Api\Representation getJsonLd()
            'name' => 'o:description',
            'type' => 'Text',
            'options' => [
                'label' => 'Description', // @translate
            ],
            'attributes' => [
                'id' => 'comment',
            ],
        ]);


        //if the other option from the view won't work, this is a kinda clunky option. Better would be to use some JS
        //to make make an interactive grab-and-put form
//        $this->add(
//            array('name' => 'o:user_add[]',
//            'type' => 'Select',
//            'options' => array(
//                'label' => 'Add a User to the Team',
//                'empty_option' => 'Select a User',
//                'value_options' => array()
//            )
//        ));
//
//        $this->add([
//            'name' => 'o:user_remove',
//            'type' => 'Select',
//            'options' => array(
//                'label' => 'Remove a User from the Team',
//                'empty_option' => 'Select a User',
//                'value_options' => array()
//            )
//        ]);

//        $this->add([
//            'type' => 'submit',
//            'name' => 'submit',
//            'attributes' => [
//                'value' => 'Update Team',
//            ],
//        ]);
    }

}
