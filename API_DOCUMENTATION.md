# 📚 Stasiun Kerang API Documentation

Base URL: `http://your-domain.com/api`

## 📋 Table of Contents
- [Response Format](#response-format)
- [HTTP Status Codes](#http-status-codes)
- [Authentication](#authentication)
- [Orders](#orders)
- [Menu Management](#menu-management)
- [Kitchen](#kitchen)
- [Payments](#payments)
- [Reports](#reports)
- [Dashboard](#dashboard)
- [Tables](#tables)
- [Settings](#settings)

---

## Response Format

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    // Response data
  },
  "timestamp": "2026-01-06 10:30:00"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "timestamp": "2026-01-06 10:30:00"
}
```

---

## HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | OK - Request successful |
| 201 | Created - Resource created successfully |
| 400 | Bad Request - Invalid input |
| 401 | Unauthorized - Authentication required |
| 404 | Not Found - Resource not found |
| 405 | Method Not Allowed |
| 500 | Internal Server Error |

---

## Authentication

### Login
**Endpoint:** `POST /api/auth.php?action=login`

**Request Body:**
```json
{
  "username": "admin",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "user": {
      "id": 1,
      "username": "admin",
      "full_name": "Administrator",
      "email": "admin@stasiun-kerang.com",
      "role": "admin",
      "avatar": null
    },
    "session_token": "sess_abc123xyz"
  },
  "timestamp": "2026-01-06 10:30:00"
}
```

### Logout
**Endpoint:** `POST /api/auth.php?action=logout`

**Response:**
```json
{
  "success": true,
  "message": "Logout berhasil",
  "timestamp": "2026-01-06 10:30:00"
}
```

### Register New User
**Endpoint:** `POST /api/auth.php?action=register`

**Request Body:**
```json
{
  "username": "newuser",
  "email": "user@example.com",
  "password": "securepassword",
  "full_name": "John Doe",
  "role": "cashier",
  "phone": "081234567890"
}
```

**Roles:** `admin`, `cashier`, `kitchen`, `waiter`

### Check Session
**Endpoint:** `GET /api/auth.php?action=check`

**Response:**
```json
{
  "success": true,
  "message": "Session active",
  "data": {
    "user_id": 1,
    "username": "admin",
    "role": "admin"
  },
  "timestamp": "2026-01-06 10:30:00"
}
```

---

## Orders

### Get All Orders
**Endpoint:** `GET /api/orders.php`

**Query Parameters:**
- `status` (optional): `pending`, `preparing`, `ready`, `completed`, `cancelled`
- `date` (optional): Format `Y-m-d` (default: today)
- `limit` (optional): Number of results (default: 100)
- `offset` (optional): Offset for pagination (default: 0)

**Example:** `GET /api/orders.php?status=pending&date=2026-01-06`

**Response:**
```json
{
  "success": true,
  "message": "Orders retrieved successfully",
  "data": {
    "orders": [
      {
        "id": 1,
        "order_number": "ORD-20260106-0001",
        "table_number": "T01",
        "customer_name": "John Doe",
        "customer_phone": "081234567890",
        "waiter_name": "Waiter Name",
        "order_type": "dine_in",
        "status": "pending",
        "subtotal": 200000,
        "tax": 20000,
        "service_charge": 10000,
        "discount": 0,
        "total": 230000,
        "total_items": 3,
        "created_at": "2026-01-06 10:30:00"
      }
    ]
  },
  "timestamp": "2026-01-06 10:30:00"
}
```

### Get Order by ID
**Endpoint:** `GET /api/orders.php?id={id}`

**Response:**
```json
{
  "success": true,
  "message": "Order retrieved successfully",
  "data": {
    "order": {
      "id": 1,
      "order_number": "ORD-20260106-0001",
      "table_number": "T01",
      "items": [
        {
          "id": 1,
          "menu_name": "Kerang Asam Manis",
          "quantity": 2,
          "price": 85000,
          "subtotal": 170000,
          "notes": "Pedas sedang",
          "status": "pending"
        }
      ]
    }
  }
}
```

### Create New Order
**Endpoint:** `POST /api/orders.php`

**Request Body:**
```json
{
  "table_id": 1,
  "customer_name": "John Doe",
  "customer_phone": "081234567890",
  "user_id": 1,
  "order_type": "dine_in",
  "items": [
    {
      "menu_item_id": 1,
      "quantity": 2,
      "price": 85000,
      "notes": "Pedas sedang"
    }
  ],
  "notes": "Tambahan catatan order",
  "discount": 0
}
```

**Response:**
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "order_id": 1,
    "order_number": "ORD-20260106-0001",
    "total": 230000
  },
  "timestamp": "2026-01-06 10:30:00"
}
```

### Update Order
**Endpoint:** `PUT /api/orders.php?id={id}`

**Request Body:**
```json
{
  "status": "preparing",
  "notes": "Updated notes"
}
```

**Status Values:** `pending`, `preparing`, `ready`, `completed`, `cancelled`

### Cancel Order
**Endpoint:** `DELETE /api/orders.php?id={id}`

**Response:**
```json
{
  "success": true,
  "message": "Order cancelled successfully",
  "timestamp": "2026-01-06 10:30:00"
}
```

---

## Menu Management

### Get All Menu Items
**Endpoint:** `GET /api/menu.php`

**Query Parameters:**
- `category` (optional): Category ID
- `search` (optional): Search term
- `available` (optional): `true` or `false`

**Response:**
```json
{
  "success": true,
  "message": "Menu items retrieved",
  "data": {
    "items": [
      {
        "id": 1,
        "category_id": 1,
        "category_name": "Seafood",
        "name": "Kerang Asam Manis",
        "description": "Kerang segar dengan saus asam manis",
        "price": 85000,
        "cost_price": 55000,
        "image": null,
        "is_available": true,
        "is_featured": true,
        "preparation_time": 15,
        "calories": 320,
        "spicy_level": "medium"
      }
    ]
  }
}
```

### Get Menu Item by ID
**Endpoint:** `GET /api/menu.php?id={id}`

### Create Menu Item
**Endpoint:** `POST /api/menu.php`

**Request Body:**
```json
{
  "category_id": 1,
  "name": "Udang Goreng Mentega",
  "description": "Udang jumbo dengan saus mentega",
  "price": 95000,
  "cost_price": 65000,
  "is_available": true,
  "is_featured": false,
  "preparation_time": 20,
  "stock_quantity": null,
  "calories": 420,
  "spicy_level": "mild"
}
```

**Spicy Levels:** `none`, `mild`, `medium`, `hot`, `very_hot`

### Update Menu Item
**Endpoint:** `PUT /api/menu.php?id={id}`

### Delete Menu Item
**Endpoint:** `DELETE /api/menu.php?id={id}`

### Get Categories
**Endpoint:** `GET /api/menu.php?resource=categories`

**Response:**
```json
{
  "success": true,
  "message": "Categories retrieved",
  "data": {
    "categories": [
      {
        "id": 1,
        "name": "Seafood",
        "description": "Fresh seafood dishes",
        "icon": "🦐",
        "sort_order": 1,
        "is_active": true,
        "item_count": 12
      }
    ]
  }
}
```

---

## Kitchen

### Get Active Orders
**Endpoint:** `GET /api/kitchen.php`

**Response:**
```json
{
  "success": true,
  "message": "Active orders retrieved",
  "data": {
    "orders": [
      {
        "id": 1,
        "order_number": "ORD-20260106-0001",
        "table_number": "T01",
        "status": "pending",
        "elapsed_time": 5,
        "items": [
          {
            "id": 1,
            "name": "Kerang Asam Manis",
            "quantity": 2,
            "notes": "Pedas sedang",
            "preparation_time": 15,
            "status": "pending"
          }
        ]
      }
    ]
  }
}
```

### Get Kitchen Queue
**Endpoint:** `GET /api/kitchen.php?action=queue`

### Get Kitchen Statistics
**Endpoint:** `GET /api/kitchen.php?action=stats`

**Response:**
```json
{
  "success": true,
  "message": "Kitchen stats retrieved",
  "data": {
    "pending": 5,
    "preparing": 3,
    "ready": 2,
    "late": 1
  }
}
```

### Start Cooking Order
**Endpoint:** `POST /api/kitchen.php?action=start`

**Request Body:**
```json
{
  "order_id": 1
}
```

### Mark Order as Ready
**Endpoint:** `POST /api/kitchen.php?action=complete`

**Request Body:**
```json
{
  "order_id": 1
}
```

---

## Payments

### Process Payment
**Endpoint:** `POST /api/payments.php?action=process`

**Request Body:**
```json
{
  "order_id": 1,
  "amount": 230000,
  "payment_method": "cash",
  "transaction_id": null
}
```

**Payment Methods:** `cash`, `card`, `qr_code`, `transfer`

**Response:**
```json
{
  "success": true,
  "message": "Payment processed successfully",
  "data": {
    "payment_id": 1,
    "payment_number": "PAY-20260106-0001",
    "order_number": "ORD-20260106-0001"
  }
}
```

### Get Payment History
**Endpoint:** `GET /api/payments.php?action=history`

**Query Parameters:**
- `order_id` (optional): Get payment for specific order
- `start_date` (optional): Start date (Y-m-d)
- `end_date` (optional): End date (Y-m-d)

### Get Payment Methods
**Endpoint:** `GET /api/payments.php?action=methods`

---

## Reports

### Get Sales Summary
**Endpoint:** `GET /api/reports.php?action=summary`

**Query Parameters:**
- `start_date` (optional): Start date (Y-m-d)
- `end_date` (optional): End date (Y-m-d)

**Response:**
```json
{
  "success": true,
  "message": "Sales report generated",
  "data": {
    "summary": {
      "total_transactions": 248,
      "total_revenue": 28500000,
      "average_transaction": 115000,
      "unique_customers": 186
    },
    "revenue_change": 22.8,
    "period": {
      "start_date": "2026-01-01",
      "end_date": "2026-01-06"
    }
  }
}
```

### Get Sales Trend
**Endpoint:** `GET /api/reports.php?action=sales_trend`

### Get Menu Performance
**Endpoint:** `GET /api/reports.php?action=menu_performance`

**Query Parameters:**
- `limit` (optional): Number of results (default: 10)

### Get Category Breakdown
**Endpoint:** `GET /api/reports.php?action=category_breakdown`

### Get Transactions
**Endpoint:** `GET /api/reports.php?action=transactions`

### Export Report
**Endpoint:** `GET /api/reports.php?action=export&format=csv`

**Formats:** `csv`, `json`

---

## Dashboard

### Get Dashboard Statistics
**Endpoint:** `GET /api/dashboard.php?action=stats`

**Query Parameters:**
- `date` (optional): Date (Y-m-d, default: today)

**Response:**
```json
{
  "success": true,
  "message": "Statistics retrieved",
  "data": {
    "total_orders": 42,
    "total_revenue": 5200000,
    "active_orders": 8,
    "table_occupancy": 65,
    "occupied_tables": 13,
    "total_tables": 20
  }
}
```

### Get Sales Trend
**Endpoint:** `GET /api/dashboard.php?action=sales`

**Query Parameters:**
- `days` (optional): Number of days (default: 7)

### Get Top Menu
**Endpoint:** `GET /api/dashboard.php?action=top_menu`

**Query Parameters:**
- `limit` (optional): Number of results (default: 5)
- `date` (optional): Date (Y-m-d)

---

## Tables

### Get All Tables
**Endpoint:** `GET /api/tables.php`

**Query Parameters:**
- `status` (optional): `available`, `occupied`, `reserved`, `maintenance`

### Get Table by ID
**Endpoint:** `GET /api/tables.php?id={id}`

### Create Table
**Endpoint:** `POST /api/tables.php`

**Request Body:**
```json
{
  "table_number": "T10",
  "capacity": 4,
  "location": "indoor",
  "status": "available",
  "qr_code": null
}
```

### Update Table
**Endpoint:** `PUT /api/tables.php?id={id}`

### Delete Table
**Endpoint:** `DELETE /api/tables.php?id={id}`

---

## Settings

### Get All Settings
**Endpoint:** `GET /api/settings.php`

**Response:**
```json
{
  "success": true,
  "message": "Settings retrieved",
  "data": {
    "settings": {
      "restaurant_name": {
        "value": "Stasiun Kerang",
        "type": "string",
        "description": "Restaurant name"
      },
      "tax_rate": {
        "value": "0.10",
        "type": "decimal",
        "description": "Tax rate (10%)"
      }
    }
  }
}
```

### Get Setting by Key
**Endpoint:** `GET /api/settings.php?key={key}`

### Update Setting
**Endpoint:** `POST /api/settings.php` or `PUT /api/settings.php`

**Request Body:**
```json
{
  "key": "tax_rate",
  "value": "0.11",
  "type": "decimal",
  "description": "Tax rate (11%)"
}
```

---

## Error Handling

All endpoints may return error responses:

```json
{
  "success": false,
  "message": "Error description here",
  "timestamp": "2026-01-06 10:30:00"
}
```

Common error scenarios:
- Missing required fields → 400 Bad Request
- Invalid credentials → 401 Unauthorized
- Resource not found → 404 Not Found
- Database errors → 500 Internal Server Error

---

## Rate Limiting

API requests are rate limited to prevent abuse. Current limits:
- 100 requests per hour per IP address

---

## Notes

- All timestamps are in Asia/Jakarta timezone (WIB)
- All monetary values are in IDR (Indonesian Rupiah)
- Date format: `Y-m-d` (e.g., 2026-01-06)
- DateTime format: `Y-m-d H:i:s` (e.g., 2026-01-06 10:30:00)

---

**Version**: 1.0  
**Last Updated**: 2026-01-06
