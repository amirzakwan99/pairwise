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

The users table holds registered creator accounts and guest participant identities. Registered accounts require email/password; guest identities have both null. Optional participant email lives on group_members.contact_email and does not reserve or link an account. All entity IDs remain ULIDs, including guest IDs referenced by expenses and shares.

## groups

```text
id
name
created_by
currency
invite_token_hash (nullable, unique, hidden from group responses)
invite_expires_at (nullable)
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
role (owner/member)
left_at (nullable)
contact_email (nullable)
account_user_id (nullable foreign key to registered users)
created_at
updated_at
```

Add a unique constraint:

```text
group_id + user_id
group_id + account_user_id (nullable account links)
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
