<?php

// 业务项目维护。已挂载 /api/v1/business 前缀、api.v1.business. 名称和 auth:sanctum。
// 每个业务接口仍须显式配置 can 中间件；记录级操作使用 Policy。
// 示例：Route::get('customers', [CustomerController::class, 'index'])
//     ->middleware('can:business.customer.view')->name('customers.index');
