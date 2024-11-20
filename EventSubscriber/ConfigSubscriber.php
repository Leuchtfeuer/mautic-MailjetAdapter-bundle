<?php

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\EventSubscriber;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ConfigSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $route             = $event->getRequest()->get('_route'); // mautic_integration_config
        $controller        = $event->getRequest()->get('_controller'); // Mautic\IntegrationsBundle\Controller\ConfigController::editAction
        $integration       = $event->getRequest()->get('integration'); // LeuchtfeuerMailjetAdapter
        $integrationConfig = $event->getRequest()->get('integration_config'); // Array
        $method            = $event->getRequest()->getMethod(); // POST
        if (
            'mautic_integration_config' === $route
            && 'Mautic\IntegrationsBundle\Controller\ConfigController::editAction' === $controller
            && 'LeuchtfeuerMailjetAdapter' === $integration
            && 'POST' === $method
            && !empty($integrationConfig)
            && isset($integrationConfig['isPublished'])
        ) {
            $this->setFile($integrationConfig['isPublished']);
        }
    }

    private function setFile(bool $published=true): void
    {
        $jsonData = json_encode(['isPublished' => $published]);
        $file     = __DIR__.'/../Config/isPublished.php';
        if (file_exists($file)) {
            unlink($file);
        }
        file_put_contents($file, $jsonData);
    }
}
