<?php


namespace Teams\Form;


use Zend\Form\Form;

class TeamItemSetForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'itemset',
            'type' => TeamItemSetFieldset::class,
        ]);
    }}