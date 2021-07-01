<?php


namespace Teams\Form\Element;


class BlankTeamSelect extends TeamSelect
{
    protected $data_placeholder = 'Select Default Team';

    public function getValueOptions() {
        $em = $this->getEntityManager();
        $valueOptions = [];
        $teams = $em->getRepository('Teams\Entity\Team')->findAll();


        //initialize with a blank value so the default message shows up
        $valueOptions[0] = '';
        foreach ($teams as $team):
            $valueOptions[$team->getId()] = $team->getName();
        endforeach;

        $prependValueOptions = $this->getOption('prepend_value_options');
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }
        return $valueOptions;

    }


}