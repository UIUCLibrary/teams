<?php
namespace Teams\Api\Representation;

use Omeka\Api\Adapter\AdapterInterface;
use Omeka\Api\Representation\ResourceReference;
use Omeka\Api\ResourceInterface;

//legacy from deciding how much of the module to expose to the API
class TeamUserReference extends ResourceReference
{
    /**
     * @var string
     */
    protected $name;

    public function __construct(ResourceInterface $resource, AdapterInterface $adapter)
    {
        $this->name = $resource->getName();
        parent::__construct($resource, $adapter);
    }

    public function name()
    {
        return $this->name;
    }

    public function jsonSerialize()
    {
        return [
            '@id' => $this->apiUrl(),
            'o:id' => $this->id(),
            'o:name' => $this->name(),
        ];
    }
}
