# Changelog

## 1.10.0 — 2026-10-22

- Generate weekly, interval/exclusion, monthly and specific recurring events
  through the native EME engine, preserving requested local times and elapsed
  seconds. Normalize Sunday 0 to the native Sunday 7 representation.
- Require a qualified EME 3.2.26 engine and matching schema before recurrence
  creation. Older engines receive HTTP 409 before writes.
- Validate sanitized titles against the native TEXT byte limit. Verify every
  stored occurrence before reporting success; failed saves return 500 and use
  native cleanup instead of leaving an apparently complete partial series.
- Reject nonexistent or unrepresentable daylight-saving times before writing.
  Expose native inclusive duration days separately from API duration seconds.
- Delete patterns through native hooks and linked event/booking/task cleanup.
- Honor configured EME add/publish/editor/author capabilities and event ownership
  for event changes and recurrence deletion. Writers without publish permission
  create drafts. Configured edit-all users retain access without `edit_posts`.
- Omit private schedule rules and dates from public recurrence metadata; public
  occurrence dates remain available through the filtered instances endpoint.

Validation: real WordPress REST contracts in private copies, including Sunday
generation, visibility, native deletion hooks, partial/all-insert fault injection,
sanitized title boundaries, elapsed-time transitions, restricted/custom roles,
mixed-visibility schedule privacy and the legacy engine guard.

## 1.9.2 — 2026-10-22

- Anonymous event and recurrence reads now respect event visibility. Draft,
  private, trash and front-end draft records return 404. Public events and direct
  unlisted links remain readable; editor access and write permissions remain.
- Recurrence lists return one stored event per native pattern. Instance reads
  return stored occurrences inside the requested window, including their actual
  start and end times. Event responses include the associated recurrence ID.
- Preserve the legacy recurrence response's duration and field types. A newly
  created recurrence response now includes its actual pattern ID. The native
  recurrence creation rewrite is a separate qualification change.
- Build release archives from the requesting checkout even when a worktree has
  a different directory name, and validate the archive before replacing it.

Validation: focused WordPress REST contracts on private copies of both active
legacy EME sites; source PHP lint on 7.4/8.5; WordPress-Extra source checks; and a
release-package test using a deliberately renamed checkout.
