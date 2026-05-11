<?php

return [
    'issueNow' => true,
    'defaults' => [
        'municipalServiceId' => '6202300',
        'municipalServiceCode' => null,
        'municipalServiceName' => 'Desenvolvimento e licenciamento de programas de computador customizáveis',
        'description' => 'Servico prestado',
        'effectiveDatePeriod' => 'ON_PAYMENT_CONFIRMATION',
        'taxes' => [
            'retainIss' => false,
            'iss' => 0,
            'cofins' => 0,
            'csll' => 0,
            'inss' => 0,
            'ir' => 0,
            'pis' => 0,
        ],
    ],
];
