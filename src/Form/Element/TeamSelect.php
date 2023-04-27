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

    protected $options = [
        'add_user_auth' => false,
        'show_all' => false,
    ];

    public function getValueOptions(): array
    {
        $valueOptions = [];

        $user_id = $this->authenticationService->getIdentity();
        $user_role =  $this->getApiManager()->read('users',['id'=>$user_id])->getContent()->role();

        $em = $this->getEntityManager();
        $team_users = $em->getRepository('Teams\Entity\TeamUser')->findBy(['user' => $user_id]);
        $all_teams = $em->getRepository('Teams\Entity\Team')->findAll();
        //this is set to display the teams for the current user. This works in many contexts for
        //normal users, but not for admins doing maintenance or adding new users to a team
        foreach ($team_users as $team_user) {
            if ($this->getOption('add_user_auth')) {
                if (!$team_user->getRole()->getCanAddUsers()) {
                    continue;
                }
            }
            $team_name = $team_user->getTeam()->getName();
            $team_id = $team_user->getTeam()->getId();
            $valueOptions[$team_id] = $team_name;
        }

        if ($this->getOption('show_all') or $user_role == 'global_admin'){
            $otherTeamsValueOptions = [];
            $allOptions = [];
            $all_teams = $em->getRepository('Teams\Entity\Team')->findAll();
            foreach ($all_teams as $team){
                if (!array_key_exists($team->getId(),$valueOptions)){
                    $team_name = $team->getName();
                    $team_id = $team->getId();
                    $otherTeamsValueOptions[$team_id] = $team_name;
                }
            }
            $allOptions[] = ['label' => 'Your Teams', 'options' => $valueOptions];
            $allOptions[] = ['label' => 'Other Teams', 'options' => $otherTeamsValueOptions];
            return $allOptions;

        } else {

            $prependValueOptions = $this->getOption('prepend_value_options');
            if (is_array($prependValueOptions)) {
                $valueOptions = $prependValueOptions + $valueOptions;
            }

            return $valueOptions;
        }


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

    public function setPlaceholder(string $placeholder)
    {
        $this->data_placeholder = $placeholder;
    }
}
