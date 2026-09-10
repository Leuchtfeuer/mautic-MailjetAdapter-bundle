<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Factory\MailjetTransportFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $excludes = [
        'Mailer/Transport/MailjetApiTransport.php',
        'Mailer/Transport/MailjetSmtpTransport.php',
    ];
    $services->load('MauticPlugin\\LeuchtfeuerMailjetAdapterBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');

    $services->set(MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Helper\CustomEmailHelperDecorator::class)
        ->decorate(Mautic\EmailBundle\Helper\MailHelper::class, null, 10);

    $services->get(MailjetTransportFactory::class)->tag('mailer.transport_factory');
};
