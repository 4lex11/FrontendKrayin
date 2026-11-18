<?php

return [
    ['key' => 'churn', 'name' => 'churn::app.title', 'route' => [
        'admin.churn.index', 'admin.churn.show', 'admin.churn.dataset.index', 'admin.churn.dataset.explore',
        'admin.churn.dataset.customer', 'admin.churn.dataset.order',
    ], 'sort' => 6],
    // Updates require both read and update permissions.
    ['key' => 'churn.update', 'name' => 'churn::app.update_permission', 'route' => 'admin.churn.update', 'sort' => 1],
    ['key' => 'churn.review', 'name' => 'churn::app.review.permission', 'route' => 'admin.churn.review', 'sort' => 2],
];
