<?php

namespace Teams\Api\Adapter;

use Omeka\Api\Adapter\AbstractAdapter;
use Omeka\Api\Request;
use Omeka\Db\Event\Subscriber\Entity;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\User;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\Message;
use Teams\Entity\Team;
use Teams\Mvc\Controller\Plugin\TeamAuth;
use Omeka\Api\Exception;


abstract class AbstractTeamEntityAdapter extends \Omeka\Api\Adapter\AbstractEntityAdapter
{

    /**
     * @inheritDoc
     */
    abstract public function getRepresentationClass() : string;


    /**
     * @inheritDoc
     */
    abstract public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore)


    /**
     * @inheritDoc
     */
    abstract public function getResourceName();

    abstract public function getResourceDBName();

    /**
     * @inheritDoc
     */
    abstract public function getEntityClass();

    abstract public function getMappedEntityClass();

    abstract public function getMappedEntityName();

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $logger = $this->getServiceLocator()->get('Omeka\Logger');

        if (Request::CREATE === $request->getOperation()){
            $logger->err('in the validator::create');
            //validate correct payload data exists
            if(!$request->getValue('team') || !is_numeric($request->getValue('team'))){
                $logger->err('our payload needs to indicate team with a numeric value');

                $errorStore->addError('o-module-teams:team', 'Your payload needs to indicate team with a numeric value');
            } else {
                $team = $this->getEntityManager()
                    ->getRepository('Teams\Entity\Team')
                    ->findOneBy(['id'=>$request->getValue('team')]);
                if($team){
                    $logger->err('a team with that id doesnt exist');

                    $errorStore->addError('o-module-teams:team', new Message(
                        'A team with id %s does not exist.', // @translate
                        $request->getValue('team') ));

                }
            }
            if(!$request->getValue($this->getMappedEntityName()) || !is_numeric($request->getValue($this->getMappedEntityName()))){
                $errorStore->addError('o-module-teams:team', "Your payload needs to indicate {$this->getMappedEntityName()} with a numeric value");
                $logger->err("the value of {$this->getMappedEntityName()} needs to be numeric");

            }

            //validate team and resource exist

        }
        if ($errorStore->hasErrors()) {
            $validationException = new Exception\ValidationException;
            $validationException->setErrorStore($errorStore);
            throw $validationException;
        }

        $entity_index = 'o:' . $this->getMappedEntityName();
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        //does the request contain a team and resource
        $data = [];
        if (Request::CREATE === $request->getOperation()){
            $data = $request->getContent();
        } elseif (Request::DELETE === $request->getOperation()) {
            $data = $request->getId();
        }
        if (!is_array($data)){
            $errorStore->addError('o:id', new Message('The %s id must be an array.', $this->getResourceName())); // @translate
            return;
        }
        if (!array_key_exists('o:team',$data)){
            $errorStore->addError('o:team', 'The request lacks a team id.'); // @translate

        }
        if (!array_key_exists($entity_index,$data)){
            $errorStore->addError($entity_index, new Message('The request lacks a %s id.',$this->getMappedEntityName())); // @translate
        }


        //is that id a team

        $team = $this->getEntityManager()
            ->getRepository('Teams\Entity\Team')
            ->findOneBy(['id'=>$data['o:team']]);
        if (! $team) {
            $errorStore->addError('o:team', new Message(
                'A team with id = "%s" can not be found', // @translate
                $data['o:team']
            ));
        }

        //is that a resource
        $mapped_entity = $this->getEntityManager()
            ->find($this->getMappedEntityClass(), $data[$entity_index]);

        if (! $mapped_entity) {
            $errorStore->addError($entity_index, new Message(
                'A %1$s with id = "%2$s" can not be found', // @translate
                $this->getMappedEntityName(),
                $data[$entity_index]
            ));
        }

        //does the team resource already exist
        if ($team && $mapped_entity){
            if (Request::CREATE === $request->getOperation() && $this->teamEntityExists($team, $mapped_entity)){
                $errorStore->addError('o:resource', 'That team resource already exists.'); // @translate
            } elseif (Request::DELETE === $request->getOperation() && ! $this->teamEntityExists($team, $mapped_entity)){
                $errorStore->addError('o:resource', 'That team resource you are trying to delete does not exists.'); // @translate
            }
        }

    }

    //PHP 8 can implement multiple types as type hint: Resource|User|ResourceTemplate|Asset|Site
    public function teamEntityExists(Team $team, EntityInterface $entity )
    {
        $entity_name = $this->getMappedEntityName();
        return $this->getEntityManager()
            ->getRepository($this->getEntityClass())
            ->findOneBy(['team'=>$team->getId(), $entity_name => $entity->getId()]);

    }

    public function teamAuthority($request, $team, $user, $resource=null)
    {
        $em = $this->getEntityManager();
        $user = $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();
        $operation = $request->getOperation();
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $teamAuth = new TeamAuth($em, $logger);
        $teamId = 0;
        if (array_key_exists('team',$request->getContent())){
            $teamId = $request->getContent()['team'];
        } elseif (array_key_exists('o:team', $request->getContent())){
            $teamId = $request->getContent()['o:team'];
        }

        if (! $teamAuth->teamAuthorized($user, $operation, 'resource', $teamId)){
            throw new Exception\PermissionDeniedException(sprintf(
                    $this->getTranslator()->translate(
                        'Permission denied for the current user to %1$s a team resource in team_id = %2$s.'
                    ),
                    $operation, $request->getContent()['o:team'])
            );
        }
    }

    public function read(Request $request)
    {
        AbstractAdapter::read($request);
    }

    public function batchCreate(Request $request)
    {
        AbstractAdapter::batchCreate($request);
    }

    public function batchDelete(Request $request)
    {
        AbstractAdapter::batchDelete($request);
    }


    public function update(Request $request)
    {
        AbstractAdapter::update($request);
    }

    public function batchUpdate(Request $request)
    {
        AbstractAdapter::batchUpdate($request);
    }

    /**
     * @param $request
     * @return bool
     *
     * Does the user have the authority to modify the resource
     */
    public function resourceAuthority($resource, User $user ):bool
    {
        $apiManager = $this->getServiceLocator()->get('Omeka\ApiManager');


        //if the resource belongs to any team where the user has resource authority, or if the resource belongs to no team

        //iterate through the teams of the resource

        if ($user->getRole() == 'global_admin'){
            return true;
        }
        $resourceTeams = $this->getEntityManager()
            ->getRepository($this->getEntityClass())
            ->findBy([$this->getResourceDBName()=>$resource]);
        if (!$resourceTeams){
            return true;
        } else {
            $userTeams = $this->getEntityManager()
                ->getRepository('Teams\Entity\TeamUser')
                ->findBy(['user'=>$user->getId()]);

            $apiManager->search('team-user', ['user'=> $user, 'can_delete_resources'=>1], ['returnScalar' => 'team'] )->getContent();
            //if the user has a resource permission in any team the resource belongs to, return true
            foreach ($resourceTeams as $resourceTeam) {
                $resourceTeamId = $resourceTeam->getTeam()->getId();
                foreach($userTeams as $userTeam){
                    if ($resourceTeamId == $userTeam->getTeam()->getId()){
                        if ($userTeam->getRole()->getCanAddItems()){
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }
}