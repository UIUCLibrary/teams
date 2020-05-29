<?php
namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Omeka\Api\Exception\InvalidArgumentException;
use Teams\Entity\TeamUser;
use Teams\Form\TeamItemSetForm;
use Teams\Form\TeamUpdateForm;
use Teams\Form\TeamUserForm;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Stdlib\ArrayObject;
use Zend\View\Model\ViewModel;


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

    public function addTeamUser(int $team_id, int $user_id, int $role_id)
    {



        $team = $this->entityManager->find('Teams\Entity\Team', $team_id);
        $user = $this->entityManager->find('Omeka\Entity\User', $user_id);
        $role = $this->entityManager->find('Teams\Entity\TeamRole', $role_id);
        $team_user = new TeamUser($team,$user,$role);
        $this->entityManager->persist($team_user);

        //flushing here because this is a mini-form and we want to see the name pop up
        //more efficient solution would be to have JS handle the popping and batch update
        $this->entityManager->flush();
        return $team_user;
    }

    public function removeTeamUser(int $team, int $user)
    {
        $em = $this->entityManager;
        $team_user = $em->find('Teams\Entity\TeamUser', ['team' => $team, 'user' => $user]);
        $em->remove($team_user);

        //flushing here because this is a mini-form and we want to see the name pop up
        //more efficient solution would be to have JS handle the popping and batch update
        $em->flush();

    }

    public function updateRole(int $team_id, int $user_id, int $role_id)
    {
        $em = $this->entityManager;

        $team_user = $em->find('Teams\Entity\TeamUser', ['team' => $team_id, 'user'=>$user_id]);
        $user_role = $em->find('Teams\Entity\TeamRole', $role_id);
        $team_user->setRole($user_role);


        $em->flush();
    }

    public function teamUpdateAction()
    {

        $itemsetForm = $this->getForm(TeamItemSetForm::class);
        $userForm = $this->getForm(TeamUserForm::class);
        $userId = $this->identity()->getId();
        $form = $this->getForm(TeamUpdateForm::class);

        //should this really be necessary?
        $id = $this->params()->fromRoute('id');
        $id = (int) $id;


        //is a team associated with that id
        try {
            $team = $this->api()->read('team', ['id'=>$id]);
        } catch (InvalidArgumentException $exception) {
            //TODO: (error_msg) this should return an error message not silently return to teams page
            return $this->redirect()->toRoute('admin/teams');
        }




        $data = $this->api()->read('team', ['id'=>$id])->getContent();

        //TODO (refactor) this is probably a stupid way to do this

        //get all of the users and put them in an associative array id:name
        $all_u_array = array();
        $all_u_collection = $this->api()->search('users')->getContent();
        foreach ($all_u_collection as $u):
            $all_u_array[$u->id()] = $u->name();
        endforeach;

        //get the team's users and put them in an associative array id:name
        $team_u_array = array();
        $team_u_collection = $this->api()->read('team', ['id'=>$id])->getContent()->users();
        foreach($team_u_collection as $team_user):
            $team_u_array[$team_user->getUser()->getId()] = $team_user->getUser()->getName();
        endforeach;

        //get the users available to be added to the team
        $available_u_array = array_diff($all_u_array, $team_u_array);

        //TODO (refactor) was trying to see if there was an easier way to get these objects into an array but consistency is more important
        $role_query = $this->entityManager->createQuery('select partial r.{id, name} from Teams\Entity\TeamRole r');
        $roles = $role_query->getResult();
        $roles_array =  $role_query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        //create an array object to hold the contents to pre-fill the form with
        //TODO (emulate) this is the procedure to use to populate forms. Copy this.
        $fill = new ArrayObject;
        $fill['o:name'] = $data->getJsonLd()['o:name'];
        $fill['o:description'] = $data->getJsonLd()['o:description'];
        $form->bind($fill);

        //is it a post request?
        //TODO (refactor) clean up this, only send what is needed
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
                'ident' => $userId,
                'userForm' => $userForm,
                'itemsetForm' => $itemsetForm,
            ]);
        }

        if ($request->isPost()){
            $post_data = $request->getPost();

            //if the post is to add a member
            if ($post_data['addUser']){
                $team_id = $id;
                $user_id = $post_data['add-member'];
                $role_id = $post_data['member-role'];
                $newMember = $this->addTeamUser($team_id, $user_id, $role_id);

                $successMessage = sprintf("Successfully added %s as a %s",$newMember->getUser()->getName(), $newMember->getRole()->getName() );
                $this->messenger()->addSuccess($successMessage);

                return $this->redirect()->refresh();
            }

            $em = $this->entityManager;
            $team_users = $em->getRepository('Teams\Entity\TeamUser')->findBy(['team'=>$id]);
            foreach ($team_users as $tu):
                $em->remove($tu);
            endforeach;
            $em->flush();

            $team_id = $id;
            $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$team_id]);
            foreach ($post_data['User'] as $user_id => $role_id):
                $user_id = (int) $user_id;
                $role_id = (int) $role_id;

                $user = $em->getRepository('Omeka\Entity\User')->findOneBy(['id'=>$user_id]);
                $role = $em->getRepository('Teams\Entity\TeamRole')->findOneBy(['id'=>$role_id]);

                $new_tu = new TeamUser($team, $user, $role);

                $em->persist($new_tu);

            endforeach;
            $em->flush();

            $successMessage = sprintf("Successfully updated the %s team", $team->getName() );
            $this->messenger()->addSuccess($successMessage);

            return $this->redirect()->refresh();



        }


//        array_search($post_data['add-member-role'], $roles_array);




        return
            new ViewModel(['team'=>$team,
            'form' => $form,
            'id'=>$id,
            'roles'=> $roles,
            'roles_array' => $roles_array,
            'all_u_collection' => $all_u_collection,
            'team_u_collection' => $team_u_collection,
            'team_u_array'=>$team_u_array,
            'available_u_array'=>$available_u_array,
            'ident' => $userId,
            'post_data'=>$post_data,
            'userForm' => $userForm,
            'itemsetForm' => $itemsetForm,
            ]);


//            $this->redirect()->toRoute('admin/teams/detail/update', ['id'=>$id]);

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
            //wrong!! not really able to get from the param, would need to extract from the return url
            $user_id = $this->params('id');
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




    }




}
