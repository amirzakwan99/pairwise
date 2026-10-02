# Responsive frontend and user experience

Read [README.md](README.md) first. Shared interfaces and resolved decisions are defined in [03-shared-contracts.md](03-shared-contracts.md). Original section numbers are retained for traceability.

## Source section 12: Group Dashboard

The main group page should show:

```text
Langkawi Trip

Total expenses
RM1,280.00
```

Then show each member's net position.

Example:

```text
Amir
You are owed RM50

Ali
You owe RM30

Abu
You owe RM20
```

However, the main settlement section must show pairwise relationships rather than only net balances.

---

## Source section 13: Settlement Screen

Create a dedicated section:

```text
Who owes whom?
```

Example:

```text
Settlement

Amir
----------------------------
You receive from Ali     RM30
You receive from Abu     RM20

Ali
----------------------------
You pay Amir             RM30
You pay Abu              RM25

Abu
----------------------------
You pay Amir             RM20
You receive from Ali     RM25
```

When the logged-in user is Amir, emphasize:

```text
You receive RM30 from Ali
You receive RM20 from Abu
```

If the logged-in user owes someone:

```text
You owe Ali RM30
You owe Abu RM20
```

Use clear directional wording.

---

## Source section 14: Pairwise Settlement View

Also provide a matrix-style view when useful.

Example:

| From | To | Amount |
|------|----|--------|
| Amir | Ali | RM30 |
| Amir | Abu | RM20 |
| Ali | Abu | RM25 |

For larger groups, provide a readable list instead of relying only on a matrix.

---

## Source section 15: Expense Details

Users should be able to click an expense and see:

```text
Dinner
RM120.00

Paid by
Amir

Participants

Amir    RM40.00
Ali     RM40.00
Abu     RM40.00
```

Also show the resulting debts from that expense:

```text
Ali owes Amir RM40
Abu owes Amir RM40
```

---

## Source section 16: Expense History

Group page should contain an expense list:

```text
Dinner
Amir paid RM120
3 participants

Grab
Ali paid RM60
3 participants

Drinks
Abu paid RM30
3 participants
```

Allow sorting by:

- Date
- Amount
- Description

Newest expenses should appear first by default.

---

## Source section 23: Mobile-First UI

The application MUST be mobile optimized.

Design mobile-first.

Do not simply create a desktop application and shrink it.

Target:

```text
320px+
```

The UI should work comfortably on:

- iPhone-sized screens
- Android phones
- tablets
- desktop

Use responsive Tailwind breakpoints.

---

## Source section 24: Mobile Navigation

Use a bottom navigation bar on mobile:

```text
┌─────────────────────────────────┐
│                                 │
│           Content               │
│                                 │
│                                 │
├─────────────────────────────────┤
│ Groups │ + Add │ Settlements │ Me │
└─────────────────────────────────┘
```

The exact navigation can be adjusted based on UX.

The "Add Expense" action should be easy to access.

---

## Source section 25: Desktop Layout

On desktop:

```text
┌─────────────────────────────────────────────┐
│ Logo                  Groups    Profile     │
├──────────────┬──────────────────────────────┤
│              │                              │
│ Groups       │       Main Content           │
│              │                              │
│ + New Group  │                              │
│              │                              │
└──────────────┴──────────────────────────────┘
```

Avoid excessive sidebars on mobile.

---

## Source section 26: Add Expense UX

Adding an expense should be extremely fast.

Ideal flow:

```text
+ Add Expense

What was it?
[ Dinner                 ]

How much?
[ RM 120.00              ]

Who paid?
[ Amir ▼                 ]

Split between
[✓] Amir
[✓] Ali
[✓] Abu

Split
(•) Equal
( ) Custom

[ Save Expense ]
```

After saving:

```text
Expense added successfully.
```

Then refresh settlement data.

---

## Source section 27: UI Design

Use a clean modern design.

Prefer:

- Cards
- Clear typography
- Good spacing
- Large touch targets
- Subtle borders
- Minimal shadows
- Clear primary action
- Responsive tables/lists

Do not make the application visually complicated.

The most important information should be:

1. How much do I owe?
2. Who do I owe?
3. Who owes me?
4. Why?
