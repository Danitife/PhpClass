# Campus Registry instructor key

Share the portal files and the student README. Keep this key for marking and discussion. The casebook evidence describes fictional incidents, and the running portal provides controls students can verify. SQLite replaces MongoDB for local setup, so the NoSQL case is a review of the earlier MongoDB design.

## Vulnerability inventory

| Case | Vulnerability | Expected explanation and verification |
| --- | --- | --- |
| SR-01 | Broken object-level authorization (IDOR) | An authenticated user could view a different student's record. For the student profile, the server selects the student from the session. The registrar route checks the registrar role before looking up the record. A student can view their own record and receives a denial for the registrar directory. |
| SR-02 | Broken function-level authorization | An ordinary student received the privilege to approve an enrollment. The registrar role is required on the state-changing route itself. A student can submit a request, and a registrar can approve it. Student approval is denied even for a request they own. |
| SR-03 | NoSQL operator injection | A credential passed as structured data can change the meaning of a MongoDB filter if request data is passed directly into it. Require bounded strings, build the lookup from a known field, and verify the password hash separately. The current app binds the username as a SQLite value and rejects an invalid field shape. A normal sign-in still succeeds. |
| SR-04 | Stored cross-site scripting | The browser interpreted part of a stored display name as HTML. Encoding output for its destination keeps a name as literal text across views. Check that `Amina <Honours>` appears literally in both the directory and the detail page, and that routine names still render correctly. Restore the name after the demonstration. |
| SR-05 | Client-controlled role claims and privilege escalation | The older service risked accepting a stale or untrusted role from the client. The cookie contains an opaque signed session identifier. The server looks up the account role for every request, rotates the session on sign-in, and invalidates it on sign-out. Verify that signing out ends access and that the student role cannot open registrar pages. |
| SR-06 | Rate limiting based on an untrusted client identity | A counter provides little protection if the client can determine which identity is counted. The loopback server leaves Express proxy trust disabled and limits failed sign-ins by the connecting client. Have students explain how specific proxy trust and a boundary-controlled forwarding chain would matter behind a reverse proxy. Verify an ordinary valid login and describe the expected throttled response past the failure limit. |
| SR-07 | Path traversal and arbitrary file exposure | A download route must resolve a published identifier to a reviewed resource. This portal uses a fixed mapping and sends the selected guide as an attachment. The published identifier returns the guide and an unknown catalog identifier returns 404. |
| SR-08 | Configuration or backup disclosure | Private files become public if a broad document root is deployed. The Node server exposes only the asset directory, which contains the stylesheet. Confirm the stylesheet loads and the instructor Markdown and application sources are absent from Node's public routes. If real credentials were exposed, remove the published copies, rotate the credentials, and review access logs. |
| SR-09 | Business logic flaw from trusting client-supplied credits | Credits must come from the authoritative catalog. Enrollment stores the student and course identifiers, and the catalog supplies the credit value. An existing course yields its listed credits and an unknown course is rejected. |
| SR-10 | Race condition in enrollment deduplication | Overlapping submissions can create duplicate rows when uniqueness depends on a preliminary read. The database constrains each student and course pair and the insert handles a conflict in one statement. The first ordinary submission is accepted, a duplicate is rejected, and the overview shows one row. A different student may still request the same course. |
| SR-11 | Workflow or state-transition bypass | Submit always creates a pending enrollment and approve updates only pending rows. A valid student request appears pending until a registrar acts. An approval attempt for an already approved record produces no further transition. |
| SR-12 | Broken account recovery authorization | A reset grant should identify one account and purpose, expire, and be consumed exactly once. Design checks for the correct account, wrong account, expired grant, and reuse after success. Recovery is a design exercise in this project. |

## Marking

Score each case out of 5 for a total of 60 points.

- **1 point** for identifying the vulnerability or failed control.
- **1 point** for naming the affected record, action, or security property.
- **1 point** for citing evidence from the case.
- **1 point** for specifying an appropriate server-side control.
- **1 point** for reporting a valid action and a rejected or safely handled action, or defining both acceptance checks for a design case.

For the two deeper reviews, score each out of 5 for a total of 10 additional points. Look for a precise reference to the enforcement point, an explanation of how it prevents the incident, and a regression check with an observable result. For SR-12, let students cite the proposed enforcement point and its expected behavior.

The included `npm test` suite uses disposable records. For a fresh in-class walkthrough, start with `STUDENT_PORTAL_DB=:memory: npm start` on macOS or Linux. Each student can run their own instance to keep changes to fictional records independent.
