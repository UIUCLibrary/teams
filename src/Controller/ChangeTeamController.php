<?php


namespace Teams\Controller;



use Doctrine\ORM\EntityManager;
use Zend\Mvc\Controller\AbstractActionController;

class ChangeTeamController extends AbstractActionController
{

    //begin edits: adding in the entity manager
    /**
     * @var entityManager
     */
    protected $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function changeAction($user_id, $data)
    {






        $request = $this->getRequest();

        if ($request->isPost()){
            $data =  $request->getPost();
            $data['return_url'] = '/Projects_PWW/omeka-s-1-3/omeka-s_1_3/admin/item';
            return $this->redirect()->$data['return_url'];


        }

//        $em = $this->entityManager;
//        $team_user = $em->getRepository('Teams\Entity\TeamUser');
//        $old_current = $team_user->findOneBy(['user' => $user_id, 'is_current' => true]);
//        $new_current = $team_user->findOneBy(['user'=> $user_id, 'team'=>$data['team_id']]);
//        $old_current->setCurrent(null);
//        $new_current->setCurrent(true);
//        $em->flush();


//        }
    }


}