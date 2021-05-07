<?php
namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Exception\InvalidArgumentException;
use Omeka\Api\Request;
use Teams\Entity\TeamResource;
use Teams\Entity\TeamResourceTemplate;
use Teams\Entity\TeamUser;
use Teams\Form\TeamItemsetAddRemoveForm;
use Teams\Form\TeamItemSetForm;
use Teams\Form\TeamUpdateForm;
use Teams\Form\TeamUserForm;
use Laminas\EventManager\Event;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Stdlib\ArrayObject;
use Laminas\View\Model\ViewModel;


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

    public function createNamedParameter(QueryBuilder $qb, $value,
                                         $prefix = 'omeka_'
    ) {
        $index = 0;
        $placeholder = $prefix . $index;
        $index++;
        $qb->setParameter($placeholder, $value);
        return ":$placeholder";
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

    public function processItemSets(int $item_set_id){
        $resource_array = array();
        if ((int)$item_set_id>0){
            $item_set_id = (int)$item_set_id;

            //TODO: why isn't this a list?
            //add all items belonging to itemset
            foreach ($this->api()->search('items', ['item_set_id'=>$item_set_id, 'bypass_team_filter' => true])->getContent() as $item):
                $resource_array += [$item->id() => true];

                //add all media belonging to to the item
                foreach ($this->api()->search('media', ['item_id'=>$item->id(), 'bypass_team_filter' => true])->getContent() as $media):
                    $resource_array += [$media->id()=>true];
                endforeach;
            endforeach;
        }
        //add itemset itself
        $resource_array += [$item_set_id => true];

        return $resource_array;
    }

    public function processResources($request, $team, $existing_resources, $existing_resources_templates, bool $delete = false){
        $resource_array = array();
        $resource_template_array = array();


        if ($delete == false){
            $collection = 'addCollections';
        } else{
            $collection = 'rmCollections';
        }

            //get ids of itemsets and their descendents
            if (isset($request->getPost($collection)['o:itemset'])){
                foreach ($request->getPost($collection)['o:itemset'] as $item_set_id):
                    $resource_array += $this->processItemSets($item_set_id);
                endforeach;
            }

            //get ids of things the user owns
            if (isset($request->getPost($collection)['o:user'])){
                foreach ($request->getPost($collection)['o:user'] as $user_id):
                    if ((int)$user_id>0){
                        $user_id = (int)$user_id;
                        foreach ($this->api()->search('items', ['owner_id' => $user_id, 'bypass_team_filter'=>true])->getContent() as $item):

                            $resource_array += [$item->id() => true];

                            foreach ($this->api()->search('media', ['item_id'=>$item->id(), 'bypass_team_filter' => true])->getContent() as $media):
                                $resource_array += [$media->id() => true];
                            endforeach;

                        endforeach;

                        //also get the users itemsets
                        foreach ($this->api()->search('item_sets', ['owner_id' => $user_id, 'bypass_team_filter'=>true])->getContent() as $itemSet):
                            $resource_array += $this->processItemSets($itemSet->id());
                        endforeach;

                        //also ge the user's resource templates
                        $rts = $this->entityManager->getRepository('Omeka\Entity\ResourceTemplate')->findBy(['owner'=>$user_id]);
                        foreach ($rts as $rt):
                            $resource_template_array[$rt->getId()] = true;
                        endforeach;
                    }
                endforeach;
            }

            if ($delete == false){
                //remove elements that are already part of the team to prevent integrity constraint violation

                //resources remove existing from the add list
                foreach ($existing_resources as $resource):
                    $rid = $resource->getResource()->getId();
                    if (array_key_exists($rid, $resource_array)){
                        unset($resource_array[$rid]);
                    }
                endforeach;

                //resource templates remove existing from the add list
                foreach ($existing_resources_templates as $resource):
                    $rid = $resource->getResourceTemplate()->getId();
                    if (array_key_exists($rid, $resource_template_array)){
                        unset($resource_template_array[$rid]);
                    }
                endforeach;

                //add the resources to the team
                foreach ($resource_array as $resource_id => $value):
                    $resource = $this->entityManager->getRepository('Omeka\Entity\Resource')
                        ->findOneBy(['id'=>$resource_id]);
                    $team_resource = new TeamResource($team, $resource);
                    $this->entityManager->persist($team_resource);
                endforeach;

                //add the resource templates to the team
                foreach ($resource_template_array as $resource_id => $value):
                    $resource = $this->entityManager->getRepository('Omeka\Entity\ResourceTemplate')
                        ->findOneBy(['id'=>$resource_id]);
                    $team_resource = new TeamResourceTemplate($team, $resource);
                    $this->entityManager->persist($team_resource);
                endforeach;

                $this->entityManager->flush();
            }


            else {
                //remove resources
                foreach (array_keys($resource_array) as $resource_id):
                    $team_resource = $this->entityManager->getRepository('Teams\Entity\TeamResource')
                        ->findOneBy(['resource'=>$resource_id, 'team'=>$team]);
                    if ($team_resource){
                    $this->entityManager->remove($team_resource);}
                endforeach;

                //remove resource templates
                foreach (array_keys($resource_template_array) as $resource_id):
                    $team_resource_template = $this->entityManager->getRepository('Teams\Entity\TeamResourceTemplate')
                        ->findOneBy(['resource_template'=>$resource_id, 'team'=>$team]);
                    if ($team_resource_template){
                        $this->entityManager->remove($team_resource_template);}
                endforeach;
                $this->entityManager->flush();
                }
    }

    public function teamUpdateAction()
    {

        $itemsetForm = $this->getForm(TeamItemsetAddRemoveForm::class);
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

        $criteria = ['id' => $id];

        $qb = $this->entityManager->createQueryBuilder();
        $entityClass = 'Teams\Entity\Team';

        $qb->select('omeka_root')->from($entityClass, 'omeka_root');
        foreach ($criteria as $field => $value) {
            $qb->andWhere($qb->expr()->eq(
                "omeka_root.$field",
                $this->createNamedParameter($qb, $value)
            ));
        }
        $qb->setMaxResults(1);

        $entity = $qb->getQuery()->getOneOrNullResult();


        $data = $this->api()->read('team', ['id'=>$id])->getContent();
        $request = new Request('update','team');
        $event = new Event('api.hydrate.pre', $this, [
            'entity' => $entity,
            'request' => $request,
        ]);
        $this->getEventManager()->triggerEvent($event);


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
                'itemsetForm' => $itemsetForm,
            ]);
        }


        $em = $this->entityManager;
        $qb = $em->createQueryBuilder();
        $existing_resources = $qb->select('tr')
            ->from('Teams\Entity\TeamResource', 'tr')
            ->where('tr.team = :team_id')
            ->setParameter('team_id', $id)
            ->getQuery()
            ->getResult();

        $existing_resource_templates = $qb->select('trt')
            ->from('Teams\Entity\TeamResourceTemplate', 'trt')
            ->where('trt.team = :team_id')
            ->setParameter('team_id', $id)
            ->getQuery()
            ->getResult();

        if ($request->isPost()){
            $post_data = $request->getPost();

            //first update the team name and description
            $qb = $this->entityManager->createQueryBuilder();
            $qb->update('Teams\Entity\Team', 'team')
                ->set('team.name', '?1')
                ->set('team.description', '?2')
                ->where('team.id = ?3')
                ->setParameter(1, $post_data['o:name'])
                ->setParameter(2, $post_data['o:description'])
                ->setParameter(3, $id)
                ->getQuery()
                ->execute();

            //if they clicked the add user button, just add a member and refresh
            //TODO: return the form as filled out with whatever changes they made or use Ajax

            //if they actually click on the add user button
            if ($post_data['addUser']){
                $team_id = $id;
                $user_id = $post_data['add-member'];
                $role_id = $post_data['member-role'];
                $newMember = $this->addTeamUser($team_id, $user_id, $role_id);

                $successMessage = sprintf("Successfully added %s as a %s",$newMember->getUser()->getName(), $newMember->getRole()->getName() );
                $this->messenger()->addSuccess($successMessage);

                return $this->redirect()->refresh();
            }

            //remove all team users and add the ones that are active in the form
            $team_users = $em->getRepository('Teams\Entity\TeamUser')->findBy(['team'=>$id]);
            foreach ($team_users as $tu):
                $em->remove($tu);
            endforeach;
            $em->flush();

            $team_id = $id;
            $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$team_id]);

            if ($post_data['UserRole']){
                foreach ($post_data['UserRole'] as $user_id => $role_id):
                    $user_id = (int) $user_id;
                    $role_id = (int) $role_id;

                    if ($post_data['UserCurrent'][$user_id] == 1){
                        $current = 1;
                    }else {$current = null;}

                    $user = $em->getRepository('Omeka\Entity\User')->findOneBy(['id'=>$user_id]);
                    $role = $em->getRepository('Teams\Entity\TeamRole')->findOneBy(['id'=>$role_id]);

                    $new_tu = new TeamUser($team, $user, $role);
                    $new_tu->setCurrent($current);

                    $em->persist($new_tu);

                endforeach;
                $em->flush();
            }



            //first delete then add resources to team
            $this->processResources($request, $team, $existing_resources, $existing_resource_templates, true);
            $this->processResources($request, $team, $existing_resources, $existing_resource_templates, false);



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
            $team = $new_current->getTeam();

            //the sites for the team the user just switched to

            $team_sites = $team->getTeamSites();
            $site_ids = [];
            foreach ($team_sites as $team_site):
                $site_ids[] = strval($team_site->getSite()->getId());
            endforeach;

            //update so those are the user's default sites for items
            $settingId = 'default_item_sites';
            $settingValue = $site_ids;
            $this->userSettings()->set($settingId, $settingValue, $user_id);

//            $em->flush();


            return $this->redirect()->toUrl($data['return_url']);


        }




    }




}
