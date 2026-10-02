# Tests, seed data and acceptance

Read [README.md](README.md) first. Shared interfaces and resolved decisions are defined in [03-shared-contracts.md](03-shared-contracts.md). Original section numbers are retained for traceability.

## Source section 32: Example Calculation Test

Input:

```text
Expense 1:
RM120
Paid by Amir
Split:
Amir RM40
Ali RM40
Abu RM40

Expense 2:
RM60
Paid by Ali
Split:
Amir RM20
Ali RM20
Abu RM20

Expense 3:
RM30
Paid by Abu
Split:
Amir RM10
Ali RM10
Abu RM10
```

Raw debts:

```text
Ali → Amir RM40
Abu → Amir RM40

Ali → Amir RM20
Abu → Ali RM20

Amir → Abu RM10
Ali → Abu RM10
```

Pairwise net:

```text
Amir ↔ Ali

Ali → Amir RM40
Ali → Amir RM20

Result:
Ali → Amir RM20
```

```text
Amir ↔ Abu

Abu → Amir RM40
Amir → Abu RM10

Result:
Abu → Amir RM30
```

```text
Ali ↔ Abu

Abu → Ali RM20
Ali → Abu RM10

Result:
Abu → Ali RM10
```

Final:

```text
Ali → Amir RM20
Abu → Amir RM30
Abu → Ali RM10
```

The application MUST produce these results.

---

## Source section 33: Important Edge Cases

Test at minimum:

### One person pays everything

```text
3 people
1 payer
```

### Two people only

```text
Amir
Ali
```

### Person does not participate

A person can belong to the group but not participate in a particular expense.

### Unequal custom split

```text
Amir RM50
Ali RM30
Abu RM20
```

### Decimal amounts

```text
RM10.01
RM100.99
```

### Equal split with remainder

```text
RM100 / 3
```

### Multiple expenses between the same pair

Ensure debts are accumulated before pairwise netting.

### Opposing debts

```text
Amir → Ali RM100
Ali → Amir RM60
```

Result:

```text
Amir → Ali RM40
```

### Completely cancelled pair

```text
Amir → Ali RM50
Ali → Amir RM50
```

Result:

```text
No debt
```

### Expense payer not participating

This is allowed in the MVP: the payer must be an active group member but need not be a participant.

Example:

```text
Ali pays RM100
Participants:
Amir
Abu
```

Then:

```text
Amir → Ali RM50
Abu → Ali RM50
```

Do not automatically add the payer as a participant.

---

## Source section 34: Testing Requirements

Write automated tests for the settlement algorithm.

At minimum:

```text
test_single_expense
test_multiple_expenses
test_pairwise_netting
test_reverse_debt
test_zero_debt
test_three_person_settlement
test_custom_split
test_equal_split_rounding
test_non_participating_payer
test_multiple_debts_between_same_pair
```

The settlement algorithm should be unit-testable without requiring HTTP requests.

---

## Source section 35: Seed Data

Create development seed data:

Group:

```text
Langkawi Trip
```

Users:

```text
Amir
Ali
Abu
```

Expenses:

```text
Dinner       RM120    Amir
Grab         RM60     Ali
Drinks       RM30     Abu
Hotel        RM300    Amir
```

Include different split configurations. Amir is the sole registered creator; Ali and Abu are name-only guest participants. Amir authors every seeded expense while payer IDs vary.

---

## Source section 45: Final Acceptance Criteria

The MVP is complete when:

1. A user can register/login.
2. A user can create a group.
3. The creator can add named participants without email or accounts; contact email is optional.
4. The creator and active invited accounts can add/edit expenses, including expenses paid by named participants. Only the creator deletes expenses.
5. A user can select who paid.
6. A user can select participants.
7. Equal splits work.
8. Custom splits work.
9. Expense split totals are validated.
10. Multiple expenses are accumulated.
11. Pairwise debt netting works.
12. No global debt minimization is performed.
13. Settlement results are correct.
14. Expense details show how the debt was created.
15. Group totals are displayed.
16. The application works well on mobile screens.
17. Backend settlement logic has automated tests.
18. Unlinked accounts and inactive participants are denied access, including legacy registered members, original expense authors and accounts matching optional contact emails. Test invitation expiry, rotation/revocation, one-time name claims, account-per-group uniqueness, cross-group selection denial, email updates and history preservation.
19. The application has clear loading, error and empty states.
20. The project can be run locally using documented instructions.
