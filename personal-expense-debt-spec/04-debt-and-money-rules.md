# Debt calculation and money handling

Read [README.md](README.md) first. Shared interfaces and resolved decisions are defined in [03-shared-contracts.md](03-shared-contracts.md). Original section numbers are retained for traceability.

## Source section 7: Debt Calculation

This is the most important business rule.

For every expense:

```text
payer = person who paid

participant share = amount that participant should bear
```

If:

```text
payer != participant
```

create a debt:

```text
participant → payer
```

Example:

```text
Dinner = RM120
Payer = Amir
Participants = Amir, Ali, Abu
Equal split = RM40 each
```

Generate:

```text
Ali → Amir RM40
Abu → Amir RM40
```

Do not create a debt for Amir because Amir paid his own share.

---

## Source section 8: Pairwise Debt Simplification

After collecting all raw debts, simplify each pair independently.

For each unique pair:

```text
A <-> B
```

Calculate:

```text
net = A owes B - B owes A
```

Rules:

```text
if net > 0:
    A owes B net

if net < 0:
    B owes A abs(net)

if net == 0:
    no debt
```

Example:

```text
Raw:

Amir → Ali RM80
Ali → Amir RM30

Net:

Amir → Ali RM50
```

Another example:

```text
Raw:

Amir → Abu RM50
Abu → Amir RM50

Net:

No debt
```

IMPORTANT:

Do NOT perform global settlement optimization.

For example, if:

```text
Amir → Ali RM30
Amir → Abu RM20
Ali → Abu RM25
```

DO NOT transform it into:

```text
Amir → Ali RM55
```

or any other globally optimized arrangement.

The three pairwise relationships must remain visible.

---

## Source section 10: Money Handling

NEVER use floating-point numbers for financial calculations.

Use decimal values in the database.

Recommended:

```text
DECIMAL(12,2)
```

Use a consistent money-handling approach in PHP.

Avoid calculations such as:

```php
0.1 + 0.2
```

without appropriate decimal handling.

All amounts should be rounded consistently to 2 decimal places for MYR.

The total of expense splits MUST equal the expense amount.

Example:

```text
Expense = RM100.00

Splits:
RM33.33
RM33.33
RM33.34

Total = RM100.00
```

When equally splitting an amount that cannot divide evenly, distribute the remaining cents deterministically.

Example:

```text
RM100 / 3

RM33.34
RM33.33
RM33.33
```

The implementation should document and test the rounding strategy.

---

## Source section 31: Settlement Algorithm Requirements

The settlement service should be deterministic and testable.

Pseudo-code:

```text
rawDebts = empty map

for each expense:
    payer = expense.paid_by

    for each split:
        participant = split.user

        if participant == payer:
            continue

        rawDebts[participant][payer] += split.amount

for every unique pair (A, B):

    aOwesB = rawDebts[A][B] or 0
    bOwesA = rawDebts[B][A] or 0

    net = aOwesB - bOwesA

    if net > 0:
        settlement = A -> B, net

    if net < 0:
        settlement = B -> A, abs(net)

    if net == 0:
        no settlement
```

Do not introduce a global debt-minimization algorithm.

---

## Source section 46: Important Instruction to the Coding Agent

Before implementing, understand this distinction:

### We want:

```text
PAIRWISE DEBT NETTING
```

### We do NOT want:

```text
GLOBAL DEBT MINIMIZATION
```

For every pair of users:

```text
A ↔ B
```

calculate the net debt independently.

For a group of:

```text
A, B, C, D
```

the pairs are:

```text
A ↔ B
A ↔ C
A ↔ D
B ↔ C
B ↔ D
C ↔ D
```

Each pair is simplified independently.

Do not use an algorithm that attempts to minimize the total number of payments across the entire group.

The final UI must clearly show who pays whom and how much.
