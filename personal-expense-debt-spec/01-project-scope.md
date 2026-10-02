# Project scope and concepts

Read [README.md](README.md) first. Shared interfaces and resolved decisions are defined in [03-shared-contracts.md](03-shared-contracts.md). Original section numbers are retained for traceability.

## Source section 1: Project Overview

Build a personal-use web application for tracking shared expenses between a group of people.

Example:

- Amir
- Ali
- Abu

During an outing, different people may pay for different expenses.

The application records:

1. Who paid.
2. How much was paid.
3. Who participated in the expense.
4. How much each participant should bear.
5. The resulting debt relationships between every pair of people.

The application should calculate **pairwise debt simplification**.

### Important settlement rule

The application MUST NOT perform global debt minimization.

Instead, it should simplify debts **independently between every pair of people**.

For every pair:

> Amount A owes B - Amount B owes A = net amount owed between A and B.

Example:

```text
Amir owes Ali    RM50
Ali owes Amir    RM20

Result:
Amir owes Ali    RM30
```

Another pair is calculated independently:

```text
Amir owes Abu    RM30
Abu owes Amir    RM10

Result:
Amir owes Abu    RM20
```

And:

```text
Ali owes Abu     RM40
Abu owes Ali     RM15

Result:
Ali owes Abu     RM25
```

Final result:

```text
Amir → Ali    RM30
Amir → Abu    RM20
Ali  → Abu    RM25
```

Do NOT consolidate these into fewer transactions.

---

## Source section 2: Main Goals

Build a clean, modern, responsive web application optimized for:

- Desktop
- Tablet
- Mobile browsers

The application is primarily for personal/friend-group use.

The UI should be simple enough that adding an expense takes only a few seconds.

No unnecessary enterprise features.

---

## Source section 5: Core Concepts

There are four main concepts:

```text
User
Group
Expense
Expense Split
```

A user can belong to multiple groups.

A group contains multiple users.

A group contains multiple expenses.

An expense has:

- One payer
- One or more participants
- An amount
- A split configuration

---

## Source section 6: Example Use Case

Group:

```text
Langkawi Trip
```

Members:

```text
Amir
Ali
Abu
```

Expense:

```text
Dinner
RM120
Paid by: Amir
Participants:
- Amir
- Ali
- Abu
Split: Equal
```

The application records:

```text
Amir paid RM120

Amir's share = RM40
Ali's share  = RM40
Abu's share  = RM40
```

Therefore:

```text
Ali owes Amir RM40
Abu owes Amir RM40
```

Another expense:

```text
Grab
RM60
Paid by: Ali
Participants:
- Amir
- Ali
- Abu
Split: Equal
```

Result:

```text
Amir owes Ali RM20
Abu owes Ali RM20
```

Another:

```text
Drinks
RM30
Paid by: Abu
Participants:
- Amir
- Ali
- Abu
Split: Equal
```

Result:

```text
Amir owes Abu RM10
Ali owes Abu RM10
```

The app then nets each pair independently.

---

## Source section 28: Currency

MVP should support one currency per group.

Default:

```text
MYR / RM
```

Store the currency on the group.

Design the database so multiple currencies can be supported later.

Do not implement currency conversion in MVP.

---

## Source section 39: What NOT to Build in MVP

Do NOT implement:

- Global debt minimization
- Currency conversion
- Bank/payment integration
- DuitNow integration
- WhatsApp integration
- Push notifications
- Social login
- AI features
- OCR receipt scanning
- Receipt image recognition
- Complex reporting
- Multi-currency settlement
- Subscription/payment system

These can be considered later.
