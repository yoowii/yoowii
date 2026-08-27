# Yoowii engineering instructions

## Product boundary

Yoowii is both an e-commerce platform and a project fulfilment system.

- Sylius owns catalog, customer, cart, order, promotion, payment, tax and shipping concerns.
- `App\Yoowii` owns pricing calculators, print production, web projects, media projects, subscriptions, the customer portal and external automations.
- An order is a commercial record. Never use a Sylius order as the mutable project or production aggregate.
- A paid order item may create one fulfilment aggregate. Creation must be idempotent.

## Architecture rules

- Add business code below `src/Yoowii/<Module>` using `Domain`, `Application`, `Infrastructure` and `UI` only when the separation is useful.
- Domain code must not depend on controllers, HTTP clients or external APIs.
- Sylius model customizations stay below `src/Entity` and delegate Yoowii business rules to `src/Yoowii`.
- Store a server-calculated immutable snapshot of every priced configuration on the order item.
- Never trust a price or total received from the browser.
- External systems are accessed behind interfaces and through Messenger handlers.
- Payment webhooks and external callbacks must be authenticated, idempotent and safe to retry.
- Never log credentials, payment secrets, authorization headers or private customer files.
- Do not expose Mantis issues directly to customers; publish customer-safe milestones in Yoowii Flow.

## MVP constraints

- Use the Sylius storefront and admin before introducing a headless frontend.
- React is allowed for complex calculators and focused portal surfaces, not as a reason to rebuild the full checkout.
- Stripe is the first online payment provider.
- Do not mix print products, service projects and subscriptions in one checkout until mixed fulfilment is explicitly designed.
- Supplier APIs, transport APIs, advanced PDF preflight and online design editing are out of the first release.

## Commands

- `make init`
- `make up`
- `make quality`
- `make test`
- `make phpstan`
- `make cs`

## Definition of done

- Business rules have unit tests.
- Integration behavior has an integration or functional test.
- PHPStan level 9 and ECS pass.
- Database changes include a Doctrine migration.
- Public/API behavior and architecture documentation are updated.
- Idempotence, authorization, failure and retry behavior are covered when applicable.
- No unrelated generated files or secrets are committed.
