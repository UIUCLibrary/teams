<?php
namespace Teams\Form\Element;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Manager as ApiManager;
use Laminas\Authentication\AuthenticationService;
use Laminas\Form\Element\Select;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Helper\Url;

class TeamSelect extends Select
{

    /**
     * @var AuthenticationService
     */
    protected $authenticationService;
    /**
     * @var ApiManager
     */
    protected $apiManager;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    protected $data_placeholder = 'Select Teams';

    protected $data_base_url = ['resource' => 'team'];

//TODO remove any value as name options
    public function getValueOptions()
    {


        $valueOptions = [];

        //TODO get user id         $identity = $this->getServiceLocator()
        //            ->get('Omeka\AuthenticationService')->getIdentity(); $user_id = identity->getId();
        $user_id = $this->authenticationService->getIdentity();
        $em = $this->getEntityManager();
        $team_users = $em->getRepository('Teams\Entity\TeamUser')->findBy(['user' => $user_id]);
        //this is set to display the teams for the current user. This works in many contexts for
        //normal users, but not for admins doing maintenance or adding new users to a team
        foreach ($team_users as $team_user):
            $team_name = $team_user->getTeam()->getName();
            $team_id = $team_user->getTeam()->getId();
            $valueOptions[$team_id] = $team_name;
        endforeach;


        $prependValueOptions = $this->getOption('prepend_value_options');
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }
        return $valueOptions;
    }

    public function setOptions($options)
    {
        if (!empty($options['chosen'])) {
            $defaultOptions = [
                'resource_value_options' => [
                    'resource' => 'team',

                ],
                'name_as_value' => true,
            ];
            if (isset($options['resource_value_options'])) {
                $options['resource_value_options'] += $defaultOptions['resource_value_options'];
            } else {
                $options['resource_value_options'] = $defaultOptions['resource_value_options'];
            }
            if (!isset($options['name_as_value'])) {
                $options['name_as_value'] = $defaultOptions['name_as_value'];
            }

            $urlHelper = $this->getUrlHelper();

            $defaultAttributes = [
                'class' => 'chosen-select',
                'data-placeholder' => $this->data_placeholder, // @translate
                'data-api-base-url' => $urlHelper('api/default', $this->data_base_url),
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
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @param EntityManager $entityManager
     */
    public function setEntityManager(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function setAuthService(AuthenticationService $authenticationService)
    {
        $this->authenticationService = $authenticationService;
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