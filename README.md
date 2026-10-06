# BPSC Exam Scheduler

Latest update: `UPDATE-v1.2.4-FOOTER-USER-MENU-07-10-2026.md` — applied after v1.2.3.

Staff-only BPSC examination scheduling, built on the provided Laravel 13/Fortify/Tabler Choice Taking base.

For v1.1.0, apply [UPDATE-v1.1.0-BANGLA-REPORT-AUDIT.md](UPDATE-v1.1.0-BANGLA-REPORT-AUDIT.md) after the initial v1.0.0 setup.

Read [INSTALL-AND-PHASE-NOTES.md](INSTALL-AND-PHASE-NOTES.md) first. Apply this overlay only to a new copy of the specified baseline and use a separate empty database.

- [Requirements and permissions](docs/PROJECT-DECISIONS.md)
- [Android/API contract](docs/API.md)
- [Production security acceptance](docs/SECURITY-AND-DEPLOYMENT.md)
- [Verification](docs/VERIFICATION.md)

Web entry: `/login` → `/dashboard`; native app API: `/api/v1`. No public candidate frontend or default administrator/password.
