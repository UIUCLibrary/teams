<?php
namespace Teams\Service\ViewHelper;

use Teams\View\Helper\AllUserSelect;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class AllUserSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new AllUserSelect($services->get('FormElementManager'));
    }
}