# Migrations

The baseline schema and seed live in `database/schema.sql` and are applied
once on a fresh install. Every structural change after that goes here as a
numbered, re-runnable file. Never rewrite the baseline in place on a
deployed system.

Naming: `001_short_description.sql`, `002_...` and so on, applied in order.

Write each migration to be safe to run twice, mostly by guarding with
`IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, or `INSERT ... ON DUPLICATE KEY
UPDATE` where the server supports it.

Apply with:

```
mysql meridian < database/migrations/001_short_description.sql
```
