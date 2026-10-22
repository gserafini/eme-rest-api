# Changelog

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
