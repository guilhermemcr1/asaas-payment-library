<?php

return [
    'defaults' => [
        'name' => 'Payment link',
        'description' => '',
        'billingType' => 'CREDIT_CARD',
        'chargeType' => 'DETACHED',
        'dueDateLimitDays' => null,
        'maxInstallmentCount' => 5,
        'defaultEndDateDays' => 1,
        'subscriptionCycle' => '',
        'notificationEnabled' => null,
        'isAddressRequired' => null,
    ],
];
