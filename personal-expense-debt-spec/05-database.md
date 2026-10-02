# Database model

Read [README.md](README.md) first. Shared interfaces and resolved decisions are defined in [03-shared-contracts.md](03-shared-contracts.md). Original section numbers are retained for traceability.

## Source section 9: Database Design

Use UUIDs or ULIDs where practical.

## users

```text
id
name
email
password
created_at
updated_at
```

## groups

```text
id
name
created_by
currency
created_at
updated_at
```

Default currency:

```text
MYR
```

## group_members

```text
id
group_id
user_id
created_at
updated_at
```

Add a unique constraint:

```text
group_id + user_id
```

## expenses

```text
id
group_id
description
amount
paid_by
split_type
expense_date
notes
created_at
updated_at
```

Possible split types:

```text
equal
custom
```

For the MVP, implement:

```text
equal
custom
```

Percentage can be added later; do not add an accepted percentage enum value in the MVP.

## expense_splits

```text
id
expense_id
user_id
amount
created_at
updated_at
```

For custom splits, this stores each participant's exact share.

Example:

```text
Expense = RM100

Amir = RM50
Ali  = RM30
Abu  = RM20
```
