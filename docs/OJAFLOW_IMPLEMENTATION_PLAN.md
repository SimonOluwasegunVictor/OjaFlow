# OjaFlow Implementation Plan

## Product Summary

OjaFlow is a Nigerian SaaS product for small businesses, wholesalers, shop owners, and retailers. It helps business owners replace paper notebooks with a simple digital system for sales, stock, receipts, customer debts, payments, staff, and daily reports.

The product should feel like a smart business notebook, not complicated accounting software.

## Core MVP Promise

OjaFlow should answer these questions every day:

- What did we sell today?
- How much money came in?
- Who is owing us?
- What products are remaining?
- Which products are almost finished?
- Which staff handled each sale?
- Are we likely making profit?

## Core Business Flow

The MVP should revolve around this flow:

```text
Add products -> Record sales -> Reduce stock -> Track customer debt -> Record payments -> Print receipt -> View daily summary
```

Every major module should support this flow directly. Anything outside this flow should wait until the foundation is stable.

## Main User Types

### Admin / Business Owner

The admin owns the business account. They can:

- Register and create a business
- Manage products and stock
- Manage customers and customer debts
- Record sales and payments
- View reports and dashboard summaries
- Create staff accounts
- Assign staff permissions
- Configure business and receipt settings

### Staff

Staff accounts are created by the admin inside the dashboard. Staff should log in with the username and password given by the admin.

Staff should only see and do what the admin allows through permissions.

Examples:

- Can record sales
- Can view reports
- Can manage stock
- Can add customers
- Can record debt payments
- Can print receipts
- Can manage other staff

### Customer

Customers are not primary app users in the MVP. They receive receipts, debt reminders, payment confirmations, or statements through print, SMS, WhatsApp, or manual sharing.

## Auth And Access Foundation

Use roles for broad identity and permissions for specific actions.

Roles:

- `admin`
- `staff`

Statuses:

- `active`
- `inactive`

Permissions should control what staff can see and do.

Recommended first permissions:

- `view_dashboard`
- `record_sales`
- `view_sales`
- `cancel_sales`
- `manage_products`
- `adjust_stock`
- `view_stock`
- `manage_customers`
- `record_debt_payments`
- `view_reports`
- `print_receipts`
- `manage_staff`
- `manage_settings`

Important rule:

```text
Frontend permissions hide screens.
Backend permissions protect actions.
```

The backend must always enforce permissions, even if the frontend hides buttons or pages.

## Recommended Controller Structure

Keep authentication separate from business operations.

```text
AuthController
- register admin
- login
- logout
- update own profile
- change own password

StaffController
- list staff
- create staff
- update staff
- activate/deactivate staff
- reset staff password
- update staff permissions

BusinessController
- view business profile
- update business profile
- update receipt settings

ProductController
- list products
- create product
- update product
- archive product

StockController
- view stock
- adjust stock
- view stock movements

CustomerController
- list customers
- create customer
- update customer
- view customer balance/history

SaleController
- record sale
- view sales history
- view sale details
- cancel sale with audit trail

PaymentController
- record sale payment
- record debt payment
- view payment history

ReceiptController
- show receipt
- print/download receipt

ReportController
- dashboard summary
- daily sales report
- debt report
- low-stock report
- staff performance report
```

## Recommended Database Modules

### Businesses

Stores each tenant/business.

Core fields:

- `id`
- `owner_id`
- `name`
- `type`
- `phone`
- `email`
- `address`
- `logo`
- `receipt_footer`
- `settings`

### Branches

Stores physical locations under a business. A business can have one or many branches.

Core fields:

- `id`
- `business_id`
- `name`
- `phone`
- `address`
- `status`
- `is_main`

Branch rule:

```text
The business owns the product catalog.
Branches own staff assignment, stock quantities, sales, payments, receipts, and reports.
```

### Users

Stores admins and staff.

Core fields:

- `id`
- `business_id`
- `branch_id`
- `first_name`
- `last_name`
- `username`
- `email`
- `phone`
- `gender`
- `role`
- `status`
- `permissions`
- `password`

### Products

Stores items the business sells.

Core fields:

- `id`
- `business_id`
- `name`
- `sku`
- `unit`
- `cost_price`
- `selling_price`
- `quantity`
- `reorder_level`
- `status`

Product rule:

```text
Products are created once for the business and can show in all branches.
Stock quantity should not live directly on products once branches are active.
```

### Branch Product Stocks

Stores product quantity per branch.

Core fields:

- `id`
- `business_id`
- `branch_id`
- `product_id`
- `quantity`
- `reorder_level`

Example:

```text
Dangote Cement
Main Branch: 100 bags
Ado Branch: 40 bags
Ibadan Branch: 75 bags
```

### Stock Movements

Every stock change should have a reason.

Core fields:

- `id`
- `business_id`
- `product_id`
- `user_id`
- `type`
- `quantity`
- `previous_quantity`
- `new_quantity`
- `reason`
- `reference_type`
- `reference_id`

Movement types:

- `purchase`
- `sale`
- `return`
- `damage`
- `correction`
- `manual_adjustment`

### Customers

Stores customers, especially those who buy on credit.

Core fields:

- `id`
- `business_id`
- `name`
- `phone`
- `address`
- `balance`

### Sales

Stores each completed sale.

Core fields:

- `id`
- `business_id`
- `customer_id`
- `user_id`
- `receipt_number`
- `subtotal`
- `discount`
- `total`
- `amount_paid`
- `balance`
- `status`
- `sold_at`

Sale statuses:

- `paid`
- `partly_paid`
- `unpaid`
- `cancelled`

### Sale Items

Stores products inside each sale.

Core fields:

- `id`
- `sale_id`
- `product_id`
- `product_name`
- `quantity`
- `unit_price`
- `cost_price`
- `line_total`

Store product snapshot values so old receipts remain correct even if product prices change later.

### Payments

Stores money received.

Core fields:

- `id`
- `business_id`
- `sale_id`
- `customer_id`
- `user_id`
- `amount`
- `method`
- `reference`
- `paid_at`

Payment methods:

- `cash`
- `transfer`
- `pos`
- `other`

### Customer Debts

Tracks balances from unpaid or partly paid sales.

Core fields:

- `id`
- `business_id`
- `customer_id`
- `sale_id`
- `original_amount`
- `amount_paid`
- `balance`
- `due_date`
- `status`

Debt statuses:

- `outstanding`
- `partly_paid`
- `paid`
- `overdue`

## MVP Build Order

### Phase 1: Foundation

- Auth
- Business setup
- Branch setup
- Admin and staff roles
- Staff permissions
- Business-scoped middleware
- Base dashboard shell

Current middleware foundation:

- `business.member`: confirms the authenticated user belongs to a business and blocks access to route resources outside that business.
- `business.admin`: confirms the authenticated user is an admin before allowing admin-only actions such as staff creation.

Current permission foundation:

- Staff permissions are stored on `users.permissions`.
- Checkbox values should use `StaffPermission` enum values.
- Frontend permissions hide screens, while backend middleware and policies protect actions.

Current branch foundation:

- Admin registration creates the business and a `Main Branch`.
- Admins can create and update branches.
- Staff can be assigned to a branch.
- Products should later be business-wide, while stock quantities should be branch-specific.

Current policy foundation:

- `UserPolicy::manageStaff`: only admins attached to a business can manage staff.
- `UserPolicy::update`: users can update themselves; admins can update users in their own business.
- `UserPolicy::delete`: admins can delete non-admin users in their own business.

### Phase 2: Products And Stock

- Product CRUD
- Stock quantity tracking
- Reorder level
- Stock movement records
- Low-stock query

### Phase 3: Customers And Debts

- Customer CRUD
- Customer balance
- Debt creation from unpaid sales
- Debt payment recording
- Customer statement/history

### Phase 4: Sales And Receipts

- Sales cart flow
- Sale items
- Full, partial, pay-later, and split payments
- Automatic stock reduction
- Receipt number generation
- Printable receipt view

### Phase 5: Reports

- Today sales
- Money received
- Customers owing
- Estimated profit
- Low-stock products
- Recent sales
- Staff performance
- Payment method breakdown

### Phase 6: Settings

- Business profile
- Receipt footer
- Receipt size
- SMS/WhatsApp toggles
- Low-stock alert settings
- Staff permission management

## Critical Business Rules

- Every user must belong to one business, except during the admin registration transaction before business creation completes.
- Staff cannot create themselves. Admin creates staff.
- Staff visibility is controlled by permissions.
- Every sale must store the staff/admin who recorded it.
- Every stock movement must store the user who caused it.
- Every sale should reduce stock automatically.
- Every partial or unpaid sale must create or update customer debt.
- Every debt payment must reduce customer balance.
- Old receipts must not change when product prices change later.
- Admin deletion should require a business closure or ownership transfer flow.

## MVP Boundaries

OjaFlow should not start as:

- Full accounting software
- Ecommerce platform
- Delivery platform
- Payroll software
- Banking app
- Large ERP

The MVP should become excellent at:

```text
Sales, stock, receipt, and customer debt tracking for Nigerian businesses.
```

## Design And Language Principles

Use simple business language.

Prefer:

- Money Received
- Customers Owing You
- Products Remaining
- Low Stock
- Sales Today

Avoid early accounting-heavy language like:

- Accounts receivable
- Revenue collection
- Inventory variance
- Ledger reconciliation

The app should be fast, clean, mobile-friendly, and easy for non-technical owners and staff to learn.

## Current Backend Position

Staff management has been moved out of `AuthController` into `StaffController`, and MVP staff permissions are stored on `users.permissions`.

For the MVP, a JSON `permissions` column on `users` is acceptable and fast to build. Later, if permissions become complex, move to normalized permission tables.

Products, branch stock, stock movements, customers, carts, sales, debts, and debt payments are implemented. Checkout now persists one or more `sale_payments` per sale, including the bank account or POS terminal used. The dashboard reads a branch-scoped report endpoint instead of sample values. Sale items also snapshot `cost_price` so historical profit estimates remain stable when product prices change.

Business admins can configure active bank accounts and POS terminals, while payment selection and customer assignment happen in the checkout modal. Checkout confirmation, completion, receipt printing, and starting a new sale are supported in the mobile UI.

Phase 5 dashboard reporting and the first Phase 6 business and receipt settings are now implemented. The mobile experience also has a branded in-app splash while the NativePHP platform launch configuration remains deployment-specific.

The next strong backend step is receipt rendering/history and the remaining notification settings.
