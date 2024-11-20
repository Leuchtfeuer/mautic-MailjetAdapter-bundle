<?php

declare(strict_types=1);

return [
    'name'        => 'Mailjet Adapter by Leuchtfeuer',
    'description' => 'Plugin allows sending emails with Mailjet in batches via API and callback handling used for bounce management.',
    'version'     => '1.0.3',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'services'    => [
        'integrations' => [
            'mautic.integration.leuchtfeuermailjetadapter' => [
                'class' => MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Integration\LeuchtfeuerMailjetAdapterIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            'mautic.integration.leuchtfeuermailjetadapter.configuration' => [
                'class' => MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Integration\Support\ConfigSupport::class,
                'tags'  => [
                    'mautic.config_integration',
                ],
            ],
            'mautic.integration.leuchtfeuermailjetadapter.config' => [
                'class' => MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Integration\Config::class,
                'tags'  => [
                    'mautic.integrations.helper',
                ],
                'arguments' => [
                    'mautic.integrations.helper',
                ],
            ],
        ],
    ],
];
