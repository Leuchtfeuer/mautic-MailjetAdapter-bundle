<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Integration\LeuchtfeuerMailjetAdapterIntegration;

class ConfigSupport extends LeuchtfeuerMailjetAdapterIntegration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;
}
