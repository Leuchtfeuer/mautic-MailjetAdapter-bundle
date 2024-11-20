<?php

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\DependencyInjection\Compiler;

use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Factory\MailjetTransportFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ConfiguratorPass implements CompilerPassInterface
{
    public const FILE                 = __DIR__.'/../../Config/isPublished.php';

    public function process(ContainerBuilder $container): void
    {
        $isPublished = false;
        if (file_exists(self::FILE)) {
            $file        = json_decode(file_get_contents(self::FILE), true);
            $isPublished = $file['isPublished'];
        }
        if ($isPublished) {
            $container->removeDefinition(MailjetTransportFactory::class);
        }
    }
}
