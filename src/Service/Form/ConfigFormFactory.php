<?php
namespace Teams\Service\Form;

use Interop\Container\ContainerInterface;
use Teams\Form\ConfigForm;
use Laminas\ServiceManager\Factory\FactoryInterface;


class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $form = new ConfigForm();
        $globalSettings = $container->get('Omeka\Settings');
        $form->setGlobalSettings($globalSettings);

        return $form;
    }
}
