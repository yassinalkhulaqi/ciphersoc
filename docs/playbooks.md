# SOAR Playbooks

Trigger: `{severity:[...], rule_ids:[...], min_risk:int}`. Actions run in order:
`assign {assignee_id}` → `status {status}` → `comment {body}` → `create_incident {title}`.

API:
- `GET/POST /playbooks` (view/manage), `PATCH/DELETE /playbooks/{id}`
- `POST /playbooks/{id}/run {alert_ids[], dry_run}` → `{matched[], skipped[], applied[], incidents[]}`

Matching is transparent: non-matching alerts are returned in `skipped`, never silently touched.
Runs are audited (`playbook.run`) and stored in `playbook_runs`.

Seeded: `Auto-acknowledge brute force`, `Critical → incident` via `PlaybookSeeder`.
Frontend: `/playbooks` CRUD + dry-run preview + matched/skipped view.
