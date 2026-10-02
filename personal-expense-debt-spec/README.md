# Personal Group Expense & Debt App — implementation specification

Start here. This folder replaces the single large specification with focused documents. Read all MVP documents before implementation, then revisit the category relevant to your task. The app uses PHP/Laravel and React/TypeScript, is mobile optimized, and nets debts independently between each pair of people.

## Document map and reading order

| Order | File | Owns |
| --- | --- | --- |
| 1 | [01-project-scope.md](01-project-scope.md) | Purpose, concepts, MVP boundaries, currency |
| 2 | [02-architecture-and-dependencies.md](02-architecture-and-dependencies.md) | Stack, runtime/package compatibility, structure, coding standards |
| 3 | [03-shared-contracts.md](03-shared-contracts.md) | Shared IDs, money, schemas, API formats, permissions and resolved ambiguities |
| 4 | [04-debt-and-money-rules.md](04-debt-and-money-rules.md) | Raw debt, pairwise netting, exact arithmetic |
| 5 | [05-database.md](05-database.md) | Tables, relationships and migration foundation |
| 6 | [06-auth-and-groups.md](06-auth-and-groups.md) | Authentication, membership and security |
| 7 | [07-expense-workflows.md](07-expense-workflows.md) | Expense forms, mutations and recalculation |
| 8 | [08-api.md](08-api.md) | REST routes, settlement response and errors |
| 9 | [09-responsive-ui.md](09-responsive-ui.md) | Screens, mobile navigation and debt explanation |
| 10 | [10-tests-and-acceptance.md](10-tests-and-acceptance.md) | Calculation fixtures, edge cases, seed data and completion criteria |
| 11 | [11-build-and-deployment.md](11-build-and-deployment.md) | Build stages, local setup and deployment documentation |
| Later | [12-future-features.md](12-future-features.md) | Optional post-MVP features |

## Conflict and change rules

1. This README controls reading/build order and MVP scope routing. 03-shared-contracts.md controls cross-component interfaces and corrections; 02-architecture-and-dependencies.md controls package selection. Other files own their category details.
2. Do not use the old single-file specification alongside this folder as a competing source. All 47 source sections are included exactly once, with section numbers retained; necessary corrections and implementation clarifications are documented in 03-shared-contracts.md.
3. Keep one schema, money representation, authentication strategy and API contract across backend, frontend, factories, seeds and tests. Update every consumer when changing a contract.
4. Follow locked package versions and dependency compatibility checks. Stop to resolve an actual incompatible runtime or contradictory requirement before building dependent work. Do not silently change the stack or business rule.
5. Backend owns calculations and authorization. Frontend previews are advisory; persisted backend shares and returned settlements are authoritative.

## Dependency order for implementation

| Stage | Requires | Deliverable and completion gate |
| --- | --- | --- |
| 1. Foundation | Scope, architecture and contracts read | Compatible runtimes/packages selected, repository scaffold, lockfiles and frontend/backend connectivity |
| 2. Schema and calculation core | Foundation and shared contracts | Migrations/models/factories, integer-cent split logic and SettlementService; unit tests pass using the corrected three-expense fixture |
| 3. Auth and groups | Schema | Creator Sanctum session flow, creator-only policies, name-only participants with optional contact email and membership lifecycle; access tests pass |
| 4. Expense and read APIs | Calculation core, auth and groups | Transactional create/edit/delete, summary and settlements; request validation and contract tests pass |
| 5. React UI | Stable API contracts and backend flows | Forms, group dashboard/history/detail and pairwise settlement screen; mobile and error/loading/empty states verified |
| 6. Delivery | All MVP flows | Full acceptance checks, production build and accurate local/deployment README |
| 7. Optional enhancements | MVP accepted | Only separately requested future features |

This order reconciles the source's incremental plan with its backend-first instruction: build and test the calculation core early; expose authorized APIs before completing the UI. Do not build optional PWA work before MVP acceptance.

## Prompt to give a coding agent

> Read README.md and every MVP file linked in its document map before creating this project. Follow 03-shared-contracts.md for all shared interfaces and corrected examples. Implement stages in dependency order, verify the selected runtime/package versions and commit lockfiles. Keep settlement calculation in the backend and use exact money arithmetic. Simplify debts only within each pair; never minimize payments globally. Implement the MVP, run the required checks, and document how to run and deploy it. Treat 12-future-features.md as deferred scope.
