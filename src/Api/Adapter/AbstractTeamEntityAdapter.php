<?php

namespace Teams\Api\Adapter;

use Omeka\Api\Adapter\EntityAdapterInterface;
use Omeka\Api\Request;
use Omeka\Api\Resource;
use Omeka\Entity\Asset;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\ResourceTemplate;
use Omeka\Entity\Site;
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
    public function getRepresentationClass()
    {
        // TODO: Implement getRepresentationClass() method.
    }

    /**
     * @inheritDoc
     */
    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore)
    {
        // TODO: Implement hydrate() method.
    }

    /**
     * @inheritDoc
     */
    public function getResourceName()
    {
        // TODO: Implement getResourceName() method.
    }

    /**
     * @inheritDoc
     */
    public function getEntityClass()
    {
        // TODO: Implement getEntityClass() method.
    }

    abstract public function getMappedEntityClass();

    abstract public function getMappedEntityName();

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {

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
            $logger->err('The request lacks a team id.');

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

    public function teamAuthority($request)
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


}