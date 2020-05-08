<?php
namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Omeka\Api\Exception\InvalidArgumentException;
use Omeka\Form\ConfirmForm;
use Omeka\Form\ResourceBatchUpdateForm;
use Omeka\Form\ResourceForm;
use Omeka\Media\Ingester\Manager;
use Omeka\Stdlib\Message;
use phpDocumentor\Reflection\Types\Integer;
use Teams\Entity\TeamUser;
use Teams\Form\TeamUpdateForm;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Stdlib\ArrayObject;
use Zend\View\Model\ViewModel;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Form\Element;



Class UpdateController extends AbstractActionController
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function addTeamUser($team_id, $user_id, $role_id)
    {
        $team = $this->entityManager->find('Teams\Entity\Team', $team_id);
        $user = $this->entityManager->find('Omeka\Entity\User', $user_id);
        $role = $this->entityManager->find('Teams\Entity\TeamRole', $role_id);
        $team_user = new TeamUser($team,$user,$role);
        $this->entityManager->persist($team_user);

        //flushing here because this is a mini-form and we want to see the name pop up
        //more efficient solution would be to have JS handle the popping and batch update
        $this->entityManager->flush();
    }

    public function removeTeamUser($team, $user)
    {
        $em = $this->entityManager;
        $team_user = $em->find('Teams\Entity\TeamUser', ['team' => $team, 'user' => $user]);
        $em->remove($team_user);

        //flushing here because this is a mini-form and we want to see the name pop up
        //more efficient solution would be to have JS handle the popping and batch update
        $em->flush();

    }

    public function updateRole($team_id, $user_id, $role_id)
    {
        $em = $this->entityManager;

        $team_user = $em->find('Teams\Entity\TeamUser', ['team' => $team_id, 'user'=>$user_id]);
        $user_role = $em->find('Teams\Entity\TeamRole', $role_id);
        $team_user->setRole($user_role);


        $em->flush();
    }

//    public function team_roster($team)
//    {
//        $em = $this->entityManager;
//        $roster = $em->createQuery('SELECT tu FROM Teams\Entity\TeamUser tu WHERE tu.team = $team');
//
//    }
//
//    public function not_on_team()
//    {
//
//    }
    public function teamUpdateAction()
    {
//        $all_team_users = $this->entityManager->getRepository('Teams\Entity\TeamUser');
//        $all_team_users->find(['team'=>2, 'user'=>1])->getRole();

//        $my_team = $this->entityManager->find('Teams\Entity\Team', 1);
//        $my_user = $this->entityManager->find('Omeka\Entity\User', 1);
//        $my_role = $this->entityManager->find('Teams\Entity\TeamRole', 1);
//        $my_team_user = new TeamUser($my_team,$my_user,$my_role);
//        $this->entityManager->persist($my_team_user);
//        $this->entityManager->flush();


//        $new_team_user = new Team;

//        $eb = $this->entityManager->getExpressionBuilder();
//        $un = $conn->getUsername();
//        $em = EntityManager::create();
        $userId = $this->identity()->getId();
        $form = $this->getForm(TeamUpdateForm::class);
        //is there an id?
        $id = $this->params()->fromRoute('id');
        if (! $id){
            return $this->redirect()->toRoute('admin/teams');
        }

        //does a team have that id
        try {
            $team = $this->api()->read('team', ['id'=>$id]);
        } catch (InvalidArgumentException $exception) {
            return $this->redirect()->toRoute('admin/teams');
        }




        $data = $this->api()->read('team', ['id'=>$id])->getContent();

        $all_u_array = array();
        $all_u_collection = $this->api()->search('users')->getContent();
        foreach ($all_u_collection as $u):
            $all_u_array[$u->id()] = $u->name();
        endforeach;

        $team_u_array = array();
        $team_u_collection = $this->api()->read('team', ['id'=>$id])->getContent()->users();

        foreach($team_u_collection as $team_user):
            $team_u_array[$team_user->getUser()->getId()] = $team_user->getUser()->getName();
        endforeach;

        $available_u_array = array_diff($all_u_array, $team_u_array);

        $role_query = $this->entityManager->createQuery('select partial r.{id, name} from Teams\Entity\TeamRole r');
        $roles = $role_query->getResult();
        $roles_array =  $role_query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);




        //create an array object to hold the contents to pre-fill the form with
        $fill = new ArrayObject;
        $fill['o:name'] = $data->getJsonLd()['o:name'];
        $fill['o:description'] = $data->getJsonLd()['o:description'];
        $form->bind($fill);




        //is it a post request?
        $request = $this->getRequest();
        if (! $request->isPost()) {
            return new ViewModel(['team'=>$team,
                'form' => $form,
                'id'=>$id,
                'roles'=> $roles,
                'roles_array' => $roles_array,
                'all_u_collection' => $all_u_collection,
                'team_u_collection' => $team_u_collection,
                'team_u_array'=>$team_u_array,
                'available_u_array'=>$available_u_array,
                'ident' => $userId]);
        }

        $post_data = $request->getPost();


        if ($post_data['add-member-choice']){
            //becuase the datalist only contains names, not ids, get the id that matches the name
            $new_member_choice_id = array_search($post_data['add-member-choice'], $all_u_array);

            //same with role
            foreach ($roles_array as $ar):
                if ($ar['name'] == $post_data['add-member-role']){
                    $role_id = $ar['id'];
                }
                endforeach;
            $this->addTeamUser($id, $new_member_choice_id, $role_id);
        }
//        array_search($post_data['add-member-role'], $roles_array);

        $remove_member_choice_id = null;
        if ($post_data['remove-member-choice']){
            //becuase the post_datalist only contains names, not ids, get the id that matches the name
            $remove_member_choice_id = array_search($post_data['remove-member-choice'], $all_u_array);
            $this->removeTeamUser($id, $remove_member_choice_id);

        }

//        if ($post_data['submit']){
            $this->api()->update('team', $id, ['o:name'=>$post_data['o:name'], 'o:description'=>$post_data['o:description']]);
//        }

        foreach (array_keys($team_u_array) as $user_id):
            if (! $user_id == $remove_member_choice_id)
            {
                $this->updateRole($id, $user_id, $post_data[$user_id]);
            }
            endforeach;



        return
//            new ViewModel(['team'=>$team,
//            'form' => $form,
//            'id'=>$id,
//            'roles'=> $roles,
//            'roles_array' => $roles_array,
//            'all_u_collection' => $all_u_collection,
//            'team_u_collection' => $team_u_collection,
//            'team_u_array'=>$team_u_array,
//            'available_u_array'=>$available_u_array,
//            'ident' => $userId,
//            'post_data'=>$post_data]);


            $this->redirect()->toRoute('admin/teams/detail/update', ['id'=>$id]);

//        if (!empty($post_data['o:user_add'])){
//            $this->api()->create('team-user', ['o:user' => $post_data['o:user_add'], 'o:team'=> $id, 'o:role'=>1] );
//        }
//        if (!empty($post_data['o:user_remove'])){
//            $this->api()->delete('team-user', ['user_id' => $post_data['o:user_remove'], 'team_id'=> $id] );
//        }



    }

    public function roleUpdateAction()
    {

    }

    public function userAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
            $user_teams = $data['user-information']['o-module-teams:Team'];
            //wrong!!
            $user_id = $this->identity()->getId();
            $em = $this->entityManager;

            foreach ($user_teams as $team_id):
                $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team_id]);
                $user = $em->getRepository('Omeka\Entity\User')->findOneBy(['id'=>$user_id]);
                $role = $em->getRepository('Teams\Entity\TeamRole')->findOneBy(['id' => 1]);
                $team_user = new TeamUser($team,$user,$role);
                $team_user->setCurrent(null);
                $em->persist($team_user);
            endforeach;
            $em->flush();
            $request = $this->getRequest();
            $return = $request->getHeader('referer');
//            return $this->redirect()->toUrl($data['return_url']);
            return $this->redirect()->toUrl($return);
        }
    }

    public function currentTeamAction()
    {
        $user_id = $this->identity()->getId();
        $team_users = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findBy(['user'=>$user_id]);













        $request = $this->getRequest();

        if ($request->isPost()){
            $data =  $request->getPost();

            $em = $this->entityManager;
            $team_user = $em->getRepository('Teams\Entity\TeamUser');
            $old_current = $team_user->findOneBy(['user' => $user_id, 'is_current' => 1]);
            $new_current = $team_user->findOneBy(['user'=> $user_id, 'team'=>$data['team_id']]);

            if ($old_current){
                $old_current->setCurrent(null);
                $em->flush();
            }
            $new_current->setCurrent(true);
            $em->flush();
            return $this->redirect()->toUrl($data['return_url']);


        }




//        }




    }




}
