<?php
/**
 * Stasiun Kerang API Documentation
 * Main API endpoint with documentation
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$apiDoc = [
    'name' => 'Stasiun Kerang Restaurant API',
    'version' => '1.0.0',
    'description' => 'RESTful API for restaurant management system',
    'base_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/api",
    'endpoints' => [
        'authentication' => [
            'base_path' => '/auth.php',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/auth.php?action=login',
                    'description' => 'User login',
                    'body' => [
                        'username' => 'string (required)',
                        'password' => 'string (required)'
                    ]
                ],
                [
                    'method' => 'POST',
                    'path' => '/auth.php?action=logout',
                    'description' => 'User logout'
                ],
                [
                    'method' => 'POST',
                    'path' => '/auth.php?action=register',
                    'description' => 'Register new user',
                    'body' => [
                        'username' => 'string (required)',
                        'email' => 'string (required)',
                        'password' => 'string (required)',
                        'full_name' => 'string (required)',
                        'role' => 'string (required): admin|cashier|kitchen|waiter'
                    ]
                ],
                [
                    'method' => 'GET',
                    'path' => '/auth.php?action=check',
                    'description' => 'Check session status'
                ]
            ]
        ],
        'orders' => [
            'base_path' => '/orders.php',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/orders.php',
                    'description' => 'Get all orders',
                    'params' => [
                        'status' => 'string (optional): pending|preparing|ready|completed|cancelled',
                        'date' => 'date (optional): Y-m-d format',
                        'limit' => 'integer (optional)',
                        'offset' => 'integer (optional)'
                    ]
                ],
                [
                    'method' => 'GET',
                    'path' => '/orders.php?id={id}',
                    'description' => 'Get specific order'
                ],
                [
                    'method' => 'POST',
                    'path' => '/orders.php',
                    'description' => 'Create new order',
                    'body' => [
                        'table_id' => 'integer (required)',
                        'items' => 'array (required)',
                        'order_type' => 'string (required): dine_in|takeaway|delivery',
                        'customer_name' => 'string (optional)',
                        'customer_phone' => 'string (optional)'
                    ]
                ],
                [
                    'method' => 'PUT',
                    'path' => '/orders.php?id={id}',
                    'description' => 'Update order',
                    'body' => [
                        'status' => 'string (optional)',
                        'notes' => 'string (optional)'
                    ]
                ]
            ]
        ],
        'menu' => [
            'base_path' => '/menu.php',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/menu.php',
                    'description' => 'Get all menu items',
                    'params' => [
                        'category' => 'integer (optional)',
                        'search' => 'string (optional)',
                        'available' => 'boolean (optional)'
                    ]
                ],
                [
                    'method' => 'POST',
                    'path' => '/menu.php',
                    'description' => 'Create menu item',
                    'body' => [
                        'category_id' => 'integer (required)',
                        'name' => 'string (required)',
                        'price' => 'decimal (required)',
                        'description' => 'string (optional)',
                        'is_available' => 'boolean (optional)'
                    ]
                ],
                [
                    'method' => 'GET',
                    'path' => '/menu.php?resource=categories',
                    'description' => 'Get all categories'
                ]
            ]
        ],
        'kitchen' => [
            'base_path' => '/kitchen.php',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/kitchen.php',
                    'description' => 'Get active kitchen orders'
                ],
                [
                    'method' => 'GET',
                    'path' => '/kitchen.php?action=stats',
                    'description' => 'Get kitchen statistics'
                ],
                [
                    'method' => 'POST',
                    'path' => '/kitchen.php?action=start',
                    'description' => 'Start cooking order',
                    'body' => ['order_id' => 'integer (required)']
                ],
                [
                    'method' => 'POST',
                    'path' => '/kitchen.php?action=complete',
                    'description' => 'Mark order as ready',
                    'body' => ['order_id' => 'integer (required)']
                ]
            ]
        ],
        'dashboard' => [
            'base_path' => '/dashboard.php',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/dashboard.php?action=stats',
                    'description' => 'Get dashboard statistics'
                ],
                [
                    'method' => 'GET',
                    'path' => '/dashboard.php?action=sales',
                    'description' => 'Get sales trend'
                ],
                [
                    'method' => 'GET',
                    'path' => '/dashboard.php?action=top_menu',
                    'description' => 'Get top selling menu'
                ]
            ]
        ],
        'reports' => [
            'base_path' => '/reports.php',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/reports.php?action=summary',
                    'description' => 'Get sales summary report'
                ],
                [
                    'method' => 'GET',
                    'path' => '/reports.php?action=transactions',
                    'description' => 'Get transaction list'
                ],
                [
                    'method' => 'GET',
                    'path' => '/reports.php?action=export&format=csv',
                    'description' => 'Export report to CSV'
                ]
            ]
        ],
        'tables' => [
            'base_path' => '/tables.php',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/tables.php',
                    'description' => 'Get all tables'
                ],
                [
                    'method' => 'POST',
                    'path' => '/tables.php',
                    'description' => 'Create new table'
                ],
                [
                    'method' => 'PUT',
                    'path' => '/tables.php?id={id}',
                    'description' => 'Update table'
                ]
            ]
        ],
        'payments' => [
            'base_path' => '/payments.php',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/payments.php?action=process',
                    'description' => 'Process payment',
                    'body' => [
                        'order_id' => 'integer (required)',
                        'amount' => 'decimal (required)',
                        'payment_method' => 'string (required): cash|card|qr_code|transfer'
                    ]
                ],
                [
                    'method' => 'GET',
                    'path' => '/payments.php?action=history',
                    'description' => 'Get payment history'
                ]
            ]
        ],
        'settings' => [
            'base_path' => '/settings.php',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/settings.php',
                    'description' => 'Get all settings'
                ],
                [
                    'method' => 'POST',
                    'path' => '/settings.php',
                    'description' => 'Update setting',
                    'body' => [
                        'key' => 'string (required)',
                        'value' => 'string (required)'
                    ]
                ]
            ]
        ]
    ],
    'response_format' => [
        'success' => [
            'success' => true,
            'message' => 'Success message',
            'data' => 'Response data object',
            'timestamp' => 'Y-m-d H:i:s'
        ],
        'error' => [
            'success' => false,
            'message' => 'Error message',
            'timestamp' => 'Y-m-d H:i:s'
        ]
    ],
    'status_codes' => [
        200 => 'OK - Request successful',
        201 => 'Created - Resource created successfully',
        400 => 'Bad Request - Invalid input',
        401 => 'Unauthorized - Authentication required',
        404 => 'Not Found - Resource not found',
        405 => 'Method Not Allowed',
        500 => 'Internal Server Error'
    ]
];

echo json_encode($apiDoc, JSON_PRETTY_PRINT);
?>
