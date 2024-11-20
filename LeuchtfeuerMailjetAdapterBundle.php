<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle;

use Mautic\IntegrationsBundle\Bundle\AbstractPluginBundle;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LeuchtfeuerMailjetAdapterBundle extends AbstractPluginBundle
{
    public function build(ContainerBuilder $containerr): void
    {
        parent::build($containerr);

        $containerr->addCompilerPass(new DependencyInjection\Compiler\ConfiguratorPass(), PassConfig::TYPE_OPTIMIZE, -999999);
    }
}
