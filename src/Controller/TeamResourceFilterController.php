<?php
namespace Teams\Controller\TeamResourceFilterController;

use Doctrine\ORM\EntityManager;
use Omeka\Form\ConfirmForm;
use Omeka\Form\ResourceForm;
use Omeka\Form\ResourceBatchUpdateForm;
use Omeka\Media\Ingester\Manager;
use Omeka\Stdlib\Message;
use Laminas\Filter\Boolean;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class TeamResourceFilterController extends AbstractActionController
{
    //begin edits: adding in the entity manager
    /**
     * @var entityManager
     */
    protected $entityManager;


    /**
     * @var Manager
     */
    protected $mediaIngesters;

    /**
     * @param Manager $mediaIngesters
     * @param EntityManager $entityManager
     */
    public function __construct(Manager $mediaIngesters, EntityManager $entityManager)
    {
        $this->mediaIngesters = $mediaIngesters;
        $this->entityManager = $entityManager;
    }
    //end edits

    public function teamItems($resource_type, $query, $user_id, $active = true, $team_id = null)
    {

        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        if ($active){
            $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'is_current' => 1 ]);

        } else{
            $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'team' => $team_id ]);
        }

        $resources = array();
        if ($team_user) {

            $active_team_id = $team_user->getTeam()->getId();

            $team_entity = $this->entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id' => $active_team_id]);


            $per_page = 10;
            $page = $query['page'];
            $start_i = ($per_page * $page) - $per_page;
            $tr = $team_entity->getTeamResources();
            $max_i = count($tr);
            if ($max_i < $start_i + $per_page){
                $end_i = $max_i;
            }else{$end_i = $start_i + $per_page;}

            $tr = $team_entity->getTeamResources();
            for ($i = $start_i; $i < $end_i; $i++) {
                $resources[] = $this->api()->read($resource_type, $tr[$i]->getResource()->getId())->getContent();
            }
        }else{$tr=null;}

        return array('page_resource'=>$resources, 'team_resources'=>$tr);



    }



}

