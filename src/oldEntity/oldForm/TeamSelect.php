<?php
namespace Teams\Form\old;

use Omeka\Api\Manager as ApiManager;

use Zend\Form\Element\Select;
use Zend\View\Helper\Url;

class TeamSelect extends Select
{
    /**
     * @var ApiManager
     */
    protected $apiManager;

    public function getValueOptions()
    {

        //options for the dropdown
        $valueOptions = [];

        //get all of the teams
        $response = $this->getApiManager()->search('team');

        //for each one set the id as the key and the name as the value
        foreach ($response->getContent() as $representation) {
            $name = $representation->name();
            $key = $representation->id();
            $valueOptions[$key] = $name;
        }


//        $prependValueOptions = $this->getOption('prepend_value_options');
//        if (is_array($prependValueOptions)) {
//            $valueOptions = $prependValueOptions + $valueOptions;
//        }
        return $valueOptions;
    }

//    public function setOptions($options)
//    {
//
//        if (!empty($options['chosen'])) {
//            $defaultOptions = [
//                'resource_value_options' => [
//                    'resource' => 'team',
//                    'query' => [],
//                    'option_text_callback' => function ($v) {
//                        return $v->name();
//                    },
//                ],
//            ];
//            $options = $options
//                ? array_merge_recursive($defaultOptions, $options)
//                : $defaultOptions;
//
//            $urlHelper = $this->getUrlHelper();
//            $defaultAttributes = [
//                'class' => 'chosen-select',
//                'data-placeholder' => 'Select teams', // @translate
//                'data-api-base-url' => $urlHelper('api'),
//            ];
//            $this->setAttributes($defaultAttributes);
//        }
//
//        return parent::setOptions($options);
//    }



    public function setOptions($options)
    {
        if (!empty($options['chosen'])) {
//            $defaultOptions = [
//                'resource_value_options' => [
//                    'resource' => 'team',
//                    'query' => [],
//                    'option_text_callback' => function ($v) {
//                        return $v->name();
//                    },
//                ],
//                'name_as_value' => true,
//            ];
//            if (isset($options['resource_value_options'])) {
//                $options['resource_value_options'] += $defaultOptions['resource_value_options'];
//            } else {
//                $options['resource_value_options'] = $defaultOptions['resource_value_options'];
//            }
//            if (!isset($options['name_as_value'])) {
//                $options['name_as_value'] = $defaultOptions['name_as_value'];
//            }

            $urlHelper = $this->getUrlHelper();
            $defaultAttributes = [
                'class' => 'chosen-select',
                'data-placeholder' => 'Select teams', // @translate
                'data-api-base-url' => $urlHelper('api/default', ['resource' => 'groups']),
            ];
            $this->setAttributes($defaultAttributes);
        }

        return parent::setOptions($options);
    }


    /**
     * @param ApiManager $apiManager
     */
    public function setApiManager(ApiManager $apiManager)
    {
        $this->apiManager = $apiManager;
    }

    /**
     * @return ApiManager
     */
    public function getApiManager()
    {
        return $this->apiManager;
    }

    /**
     * @param Url $urlHelper
     */
    public function setUrlHelper(Url $urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return Url
     */
    public function getUrlHelper()
    {
        return $this->urlHelper;
    }
}
