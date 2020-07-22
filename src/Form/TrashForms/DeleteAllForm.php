<?php
namespace Teams\Form\TrashForms;

use Zend\Form\Form;

/**
 * General form for confirming an irreversable action in a sidebar.
 */
class DeleteAllForm extends Form
{
    public function init()
    {
        $this->add([
            'type' => 'submit',
            'name' => 'submit',
            'attributes' => [
                'value' => 'Delete All', // @translate
            ],
        ]);
    }

    public function setButtonLabel($label)
    {
        $this->get('submit')->setAttribute('value', $label);
    }
}
