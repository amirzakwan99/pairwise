# Expense creation, editing and deletion

Read [README.md](README.md) first. Shared interfaces and resolved decisions are defined in [03-shared-contracts.md](03-shared-contracts.md). Original section numbers are retained for traceability.

## Source section 11: Expense Creation

Create an expense form with:

```text
Description
Amount
Paid by
Date
Participants
Split method
```

For equal split:

```text
[✓] Amir
[✓] Ali
[✓] Abu
```

The application calculates the shares automatically.

For custom split:

```text
Amir    RM50
Ali     RM30
Abu     RM20
```

Validate that:

```text
sum(splits) == expense amount
```

before saving.

---

## Source section 17: Editing Expenses

Users with permission should be able to edit an expense.

When an expense is edited:

1. Recalculate its splits.
2. Recalculate group settlement.
3. Do not store stale settlement data.

Settlement should preferably be calculated from the source expenses rather than manually maintained.

---

## Source section 18: Deleting Expenses

Allow authorized users to delete expenses.

Before deletion:

```text
Are you sure?

This will remove:
Dinner - RM120

The group settlement will be recalculated.
```

Use soft deletes if appropriate.
