# Personal Balance — Database Documentation

## Purpose

This document describes the database structure of the **Personal Balance** application, the responsibility of each table, the key relationships between entities, and the main architectural decisions behind the data model.

It is meant to be useful for:

* future development
* onboarding and maintenance
* AI-assisted coding tools
* remembering why each table exists and how it should be used

---

## Core design principles

### 1. Database and code are in English

Table names, column names, models, and enums are in English.

### 2. UI can be in Italian

Even if the database is in English, the application interface can be fully Italian.

### 3. Transactions store only real movements

The `transactions` table contains only movements that actually happened.
It must not be used for reminders, recurring plans, or future expected payments.

### 4. Planned data is separated from actual data

Planned items live in:

* `recurring_entries`
* `recurring_entry_occurrences`
* `scheduled_entries`
* `budgets`

Actual movements live in:

* `transactions`

### 5. Amounts are always positive

The sign is not stored in the amount.
The direction is stored separately through fields like:

* `income`
* `expense`
* `transfer`

### 6. Scope is separate from category

Example:

* category = `Luce`
* scope = `Casa 1`

This is better than creating categories like `Luce Casa 1`.

### 7. Merchant is separate from category

Example:

* category = `Alimentari`
* merchant = `Sole365`

This allows analysis both by category and by merchant.

### 8. Year selection is application state, not a domain entity

There is no `financial_years` table.
The selected active year is stored in `user_settings.active_year`.
Filtering is done using actual date fields and `year` columns where applicable.

### 9. Internal ids and public uuids have different roles

The application keeps integer `id` columns as internal primary keys and foreign keys.
They remain the default reference for:

* database relations
* queries
* validation rules
* internal application logic

The `uuid` column is the public identifier for records that can be exposed outside the application layer.
It is used gradually in:

* payloads sent to Inertia / JSON consumers
* selected public URLs where exposing sequential ids is undesirable

This rollout is intentionally incremental:

* existing modules can continue using internal `id` values where needed for compatibility
* public-facing payloads should expose `uuid`
* routes should switch to `uuid` only when the change is low risk and already supported end to end

---

## Public UUID convention

All domain entities included in the UUID rollout have a nullable-to-backfilled `uuid` column with a unique index and automatic generation at model creation time.

Current convention:

* `id` = internal identifier
* `uuid` = public identifier

As of the current rollout state:

* domain payloads for settings, budget planning, years, tracked items, categories, accounts, banks, and transactions expose `uuid`
* transaction mutation routes use `uuid` in the URL for the bound transaction record
* store / update / delete logic still relies on internal ids in request payloads when that avoids frontend regressions

# Database overview

## Main domains

The database is split into these domains:

1. **User settings and master data**
2. **Accounts and balances**
3. **Import and parsing**
4. **Real financial movements**
5. **Matching, reviews, and learning support**
6. **Planning and recurring entries**
7. **Budgets and forecasts**

---

# 1. User settings and master data

## `user_settings`

### Responsibility

Stores user-level settings used by the application.

### Main use cases

* selected active year
* base currency

### Important columns

* `user_id`
* `active_year`
* `base_currency`

### Notes

This table is intentionally lightweight.
The active year is a UI/application preference, not a full business entity.

---

## `banks`

### Responsibility

Stores the list of banks.

### Main use cases

* linking real accounts to a bank
* import source identification
* matching rules scoped by bank

### Important columns

* `name`
* `slug`
* `country_code`
* `is_active`

---

## `account_types`

### Responsibility

Defines the type of an account.

### Typical values

* `bank`
* `cash`
* `card`
* `wallet`
* `savings`
* `loan`

### Notes

This keeps the `accounts` table flexible and supports non-bank money containers.

---

## `scopes`

### Responsibility

Represents logical areas where money belongs.

### Examples

* `Personale`
* `Casa 1`
* `Casa 2`
* `Cane`
* `Risparmio`

### Why it exists

Scopes let the application answer questions like:

* how much does Casa 1 cost?
* how much do I spend personally?
* how much belongs to savings-related activity?

### Important columns

* `user_id`
* `name`
* `type`
* `color`
* `is_active`

---

## `categories`

### Responsibility

Stores the financial categories used to classify movements and planned items.

### Examples

* `Stipendio`
* `Alimentari`
* `Luce`
* `Acqua`
* `Condominio`
* `Mutuo`
* `Fondo emergenze`

### Important columns

* `user_id`
* `parent_id`
* `name`
* `slug`
* `direction_type`
* `group_type`
* `sort_order`
* `is_active`

### Why `direction_type` exists

It defines the natural direction of the category:

* `income`
* `expense`
* `transfer`
* `mixed`

### Why `group_type` exists

It provides a higher-level analytical grouping:

* `income`
* `expense`
* `bill`
* `debt`
* `saving`
* `tax`
* `investment`
* `transfer`

This is more expressive than the typical Excel grouping alone.

---

## `merchants`

### Responsibility

Stores merchants, suppliers, counterparties, or payment targets.

### Examples

* `Sole365`
* `Decò`
* `Enel Energia`
* `GORI`
* `Q8`
* `Amazon`

### Important columns

* `user_id`
* `name`
* `normalized_name`
* `default_category_id`
* `is_active`

### Why it exists

A merchant is not the same thing as a category.
It enables better matching and better analytics.

---

## `merchant_aliases`

### Responsibility

Stores alias patterns that can identify a merchant from imported transaction descriptions.

### Examples

A merchant like `Sole365` can have aliases such as:

* `SOLE365`
* `SOLE 365`
* `SOLE365 NAPOLI`

### Important columns

* `merchant_id`
* `alias`
* `normalized_alias`
* `match_type`
* `priority`
* `is_active`

### Why it exists

Imported descriptions are often noisy and inconsistent.
Aliases let the system map different raw strings to one canonical merchant.

---

# 2. Accounts and balances

## `accounts`

### Responsibility

Stores all money containers used by the user.

### Examples

* main bank account
* personal card
* cash fund
* savings account
* wallet

### Important columns

* `user_id`
* `bank_id`
* `account_type_id`
* `scope_id`
* `name`
* `iban`
* `currency`
* `opening_balance`
* `current_balance`
* `is_manual`
* `is_active`

### Why it exists

This is the core financial container table.
All real transactions belong to an account.

---

## `account_opening_balances`

### Responsibility

Stores official starting balances for accounts.

### Important columns

* `account_id`
* `balance_date`
* `amount`
* `notes`
* `created_by`

### Why it exists

The application needs a known starting point.
This is separate from real transactions.

---

## `account_balance_snapshots`

### Responsibility

Stores balance snapshots at specific dates.

### Important columns

* `account_id`
* `snapshot_date`
* `balance`
* `source_type`
* `import_id`
* `notes`

### Why it exists

Useful for:

* account balance charts
* reconciliation support
* checking consistency over time
* storing balance data coming from imports

---

## `account_reconciliations`

### Responsibility

Stores a reconciliation event where a real balance is compared to the expected system balance.

### Important columns

* `account_id`
* `reconciliation_date`
* `expected_balance`
* `actual_balance`
* `difference_amount`
* `adjustment_transaction_id`
* `notes`
* `created_by`

### Why it exists

Sometimes balances do not match.
This table allows the system to track a reconciliation without rewriting history.
Optionally, an adjustment transaction can be linked.

---

# 3. Import and parsing

## `imports`

### Responsibility

Represents one uploaded import file.

### Supported source types

* `csv`
* `xlsx`
* `pdf`

### Important columns

* `user_id`
* `bank_id`
* `account_id`
* `original_filename`
* `stored_filename`
* `mime_type`
* `source_type`
* `parser_key`
* `status`
* `imported_at`
* `error_message`

### Why it exists

Keeps import history and metadata.
Each import can generate parsed rows and then real transactions.

---

## `import_rows`

### Responsibility

Stores raw parsed lines extracted from an import file.

### Important columns

* `import_id`
* `row_index`
* `raw_date`
* `raw_value_date`
* `raw_description`
* `raw_amount`
* `raw_balance`
* `raw_payload`
* `parse_status`
* `parse_error`

### Why it exists

It preserves raw imported data before normalization.
This is useful for:

* debugging parsers
* manual review
* rebuilding normalized transactions if needed

---

# 4. Real financial movements

## `transactions`

### Responsibility

Stores real money movements that actually happened.

### Important columns

* `user_id`
* `account_id`
* `import_id`
* `import_row_id`
* `scope_id`
* `category_id`
* `merchant_id`
* `transaction_date`
* `value_date`
* `posted_at`
* `direction`
* `amount`
* `currency`
* `description`
* `bank_description_raw`
* `bank_description_clean`
* `bank_operation_type`
* `counterparty_name`
* `reference_code`
* `balance_after`
* `source_type`
* `status`
* `matched_rule_id`
* `matched_sample_id`
* `match_strategy`
* `confidence_score`
* `external_hash`
* `reconciliation_key`
* `is_transfer`
* `related_transaction_id`
* `notes`

### Key idea

This is the main table for actual accounting movements.

### What should not go here

* future reminders
* planned monthly bills not yet paid
* budget lines
* abstract forecasts

### Why `related_transaction_id` exists

It supports paired movements such as transfers between accounts.

---

## `transaction_splits`

### Responsibility

Splits one real transaction into multiple sub-allocations.

### Examples

A single payment can be split into:

* part for `Alimentari`
* part for `Extra`

### Important columns

* `transaction_id`
* `category_id`
* `scope_id`
* `merchant_id`
* `amount`
* `notes`

### Why it exists

Some real movements belong to more than one category or purpose.

---

## `transaction_reviews`

### Responsibility

Stores the history of human reviews over transactions.

### Important columns

* `transaction_id`
* `reviewed_by`
* `old_category_id`
* `new_category_id`
* `old_scope_id`
* `new_scope_id`
* `old_merchant_id`
* `new_merchant_id`
* `review_action`
* `notes`

### Why it exists

The system may auto-categorize or suggest values, but human review must remain traceable.

---

# 5. Matching, automation, and learning support

## `transaction_matchers`

### Responsibility

Stores explicit matching rules used to auto-classify transactions.

### Important columns

* `user_id`
* `bank_id`
* `account_id`
* `merchant_id`
* `category_id`
* `scope_id`
* `direction`
* `match_field`
* `match_type`
* `pattern`
* `normalized_pattern`
* `confidence_score`
* `auto_confirm`
* `priority`
* `is_active`

### Example use case

If `bank_description_raw` contains `ENEL`, suggest:

* merchant = Enel Energia
* category = Luce
* scope = Casa 1

### Why it exists

It gives deterministic automation before using similarity or training data.

---

## `transaction_training_samples`

### Responsibility

Stores confirmed transaction classification examples to support future matching and learning.

### Important columns

* `user_id`
* `bank_id`
* `account_id`
* `raw_description`
* `clean_description`
* `normalized_signature`
* `category_id`
* `merchant_id`
* `scope_id`
* `confirmed_by_user`
* `usage_count`
* `last_seen_at`

### Why it exists

It acts like memory for already-confirmed transaction patterns.
This will be useful for smarter suggestions later.

---

# 6. Planning and recurring entries

## `recurring_entries`

### Responsibility

Stores recurring financial plans or expectations.

### Examples

* monthly electricity bill
* monthly salary
* monthly condominium fee

### Important columns

* `user_id`
* `account_id`
* `scope_id`
* `category_id`
* `merchant_id`
* `title`
* `direction`
* `expected_amount`
* `recurrence_type`
* `recurrence_interval`
* `recurrence_rule`
* `start_date`
* `end_date`
* `due_day`
* `auto_generate_occurrences`
* `auto_create_transaction`
* `is_active`

### Why it exists

A recurring plan is not a real transaction.
It defines a pattern expected to happen repeatedly.

---

## `recurring_entry_occurrences`

### Responsibility

Stores concrete occurrences generated from recurring entries.

### Examples

If a recurring entry is “monthly electricity bill”, occurrences become:

* January 2025 electricity bill
* February 2025 electricity bill
* March 2025 electricity bill

### Important columns

* `recurring_entry_id`
* `expected_date`
* `due_date`
* `expected_amount`
* `status`
* `matched_transaction_id`
* `converted_transaction_id`
* `notes`

### Why it exists

The application needs concrete rows to show:

* upcoming items
* overdue planned items
* matched planned items
* future comparisons against actual data

---

## `scheduled_entries`

### Responsibility

Stores one-off planned items that are not recurring.

### Examples

* annual insurance payment
* doctor visit
* one-off incoming freelance payment

### Important columns

* `user_id`
* `account_id`
* `scope_id`
* `category_id`
* `merchant_id`
* `title`
* `direction`
* `expected_amount`
* `scheduled_date`
* `status`
* `matched_transaction_id`
* `notes`

### Why it exists

Not every future event is recurring.
This table covers single scheduled items.

---

# 7. Budgets and forecasts

## `budgets`

### Responsibility

Stores month-by-month budget values per category and optionally per scope.

### Important columns

* `user_id`
* `scope_id`
* `category_id`
* `year`
* `month`
* `amount`
* `budget_type`
* `notes`

### Why it exists

Budgets are not transactions and not recurring entries.
They are monthly forecast or target values used for comparison.

### Main use cases

* monthly budget vs actual graphs
* category budget control
* annual and monthly planning

---

# Important relationship summary

## One user owns

* settings
* scopes
* categories
* merchants
* accounts
* imports
* transactions
* recurring entries
* scheduled entries
* budgets
* matching rules
* training samples

## One account has

* opening balances
* snapshots
* reconciliations
* transactions
* recurring entries
* scheduled entries
* imports

## One import has

* many import rows
* may generate many transactions

## One recurring entry has

* many recurring occurrences

## One transaction can have

* many splits
* many reviews
* one matched rule
* one matched training sample
* one related transaction

---

# Seeded demo data currently expected

The seeded development database is expected to include:

* 1 user
* 1 user settings row with active year 2025
* several banks
* account types
* default scopes
* default categories
* merchants
* merchant aliases
* 3 accounts
* opening balances
* budget rows for 2025
* around 40 real transactions across the year
* recurring entries
* recurring occurrences
* scheduled entries
* balance snapshots
* one reconciliation example
* matchers
* transaction split example
* review example
* training sample examples

This is enough to support early backend and dashboard development.

---

# Current intended development order

1. Verify seeded data quality
2. Define the required application views
3. Build CRUD and controller layer
4. Build supporting services and repositories only where they simplify logic
5. Build report queries
6. Build dashboard and charts last

---

# Notes for future development

## Good candidates for services

* transaction filtering and reporting
* budget vs actual comparison
* import normalization pipeline
* recurring occurrence generation
* reconciliation calculations
* matcher execution logic

## Good candidates for repositories only if complexity grows

* transactions
* accounts
* dashboard/report aggregations

Repositories should not be introduced by default if they only wrap simple Eloquent queries.

---

# Final architectural summary

The database has been designed to keep these concerns separate:

* **real data**
* **planned data**
* **import raw data**
* **classification support**
* **review and correction history**
* **budget and forecast data**
* **account balance control**

This separation is the main reason the model stays robust and scalable.
