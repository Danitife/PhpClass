# Campus Registry — Student Security Exploration Guide

> Run the portal on your computer using the demo accounts below. The **Security casebook** contains 12 fictional incident reports. For each case, investigate the evidence and check how Campus Registry handles the same situation.

## 0 · Setup

1. Install **Node.js 22.13 or newer**. Open a terminal in the `student-portal` folder, where this README is located.
2. Install the dependencies and start a fresh classroom database. On macOS or Linux, run:

   ```sh
   npm ci
   STUDENT_PORTAL_DB=:memory: npm start
   ```

   On Windows PowerShell, run:

   ```powershell
   npm ci
   $env:STUDENT_PORTAL_DB = ':memory:'
   npm start
   ```

3. Wait for the terminal to report that Campus Registry is running. Open [http://127.0.0.1:4317](http://127.0.0.1:4317).
4. Sign in as **Amina** and select **Security casebook**. Expand **SR-01** to see the fictional evidence, the investigation question, and the portal check.

Keep the server running as you work through the guide. To restart from the original demo records, stop it with **Ctrl+C** and repeat the start command. If you want to save your work across restarts, start with `npm start` without the classroom database setting. The default database file is `~/.student-portal/registry.sqlite`.

### Seed accounts

| Name | Role | Username | Password |
| --- | --- | --- | --- |
| Amina Bello | Student | `amina` | `Amina-Classroom-27` |
| Tobi Okafor | Student | `tobi` | `Tobi-Classroom-27` |
| Zainab Musa | Student | `zainab` | `Zainab-Classroom-27` |
| Nneka Adeyemi | Registrar | `registrar` | `Registrar-Classroom-27` |

> To switch accounts, use **Sign out** and sign in again. To keep both roles open at once, use separate browser profiles. Ordinary tabs in the same profile share a sign-in.

### How to work through a case

1. Open the matching case ID in **Security casebook** and read the fictional evidence.
2. Follow the steps in Part A. For paths like `/profile`, put the path after `http://127.0.0.1:4317` in your browser.
3. Record what happened and use the evidence to name the likely weakness. Use the submission table in Part C.

## Map from portal feature to source file

| Portal feature | Cases | Where to look |
| --- | --- | --- |
| My profile and Student directory | SR-01, SR-04 | `app.js`, `views.js` |
| Enrollment requests | SR-02, SR-09, SR-10, SR-11 | `app.js`, `data.js`, `test/portal.test.js` |
| Sign-in and sessions | SR-03, SR-05, SR-06 | `app.js` |
| Course downloads and public assets | SR-07, SR-08 | `app.js`, `public/` |
| Account recovery | SR-12 | Casebook evidence and your proposed design |

---

## Part A — Work through the 12 cases

### 1 · SR-01 — A profile appears in the wrong session

1. Sign in as **Amina**. Open **My profile** and note the student number.
2. While still signed in as Amina, open `/students` in the browser.
3. Switch to **registrar**. Open **Student directory** and search for `ST-1002`. Open the matching record.

**Expected result.** Amina sees her own record (`ST-1001`) and receives **Access denied** on the directory page. The registrar can open Tobi's record (`ST-1002`). Record what the server should check before returning a student record.

### 2 · SR-02 — A student appears in the approval audit

1. Sign in as **Amina** and open **Overview**. Find **Application Security** (`SEC 210`), which starts as **Pending**.
2. Open `/requests` while signed in as Amina.
3. Switch to **registrar**. Open **Enrollment requests** and approve the **SEC 210** row for **Amina Bello**.
4. Switch back to **Amina** and check **Overview**.

**Expected result.** Amina receives **Access denied** on the review page. The registrar can approve the request, and Amina then sees **Approved**. The automated test in SR-10 also verifies that the approval action itself rejects a student account. Record who is allowed to submit a request and who can approve it.

### 3 · SR-03 — A credential field arrives in an unexpected shape

1. On the sign-in page, enter `amina` and an incorrect password once. Record the message.
2. Sign in using Amina's password from the seed accounts table.
3. In `app.js`, find `POST /login`. Follow the credential fields from the form checks to the user lookup and password verification. Predict what the handler would do if one field arrived as a list of values. Compare that decision with the older MongoDB sign-in trace in the casebook.

**Expected result.** Incorrect credentials show **The username or password was incorrect.** Valid credentials open **Overview**. A field that arrives as a list fails the format check before the account lookup. Record that last finding as a code-review result.

### 4 · SR-04 — A name becomes part of the page layout

1. Sign in as **registrar**. From **Student directory**, open **Amina Bello**.
2. Change her display name to `Amina <Honours>` and select **Save record**.
3. Check how the name appears on the record page. Open **Student directory** and confirm the same display there.
4. Open the record again, restore `Amina Bello`, and save.

**Expected result.** Both pages show the full literal text `Amina <Honours>`, including the angle brackets. Record where the view turns the stored name into browser-safe text.

### 5 · SR-05 — A stale role appears in a browser backup

1. Sign in as **Amina**. In Chrome, open developer tools, go to **Application**, and expand **Cookies** for the portal origin.
2. Find the `campus.sid` cookie. In `app.js`, find the middleware that loads the signed-in user. Check where it gets the role.
3. Select **Sign out**, then open `/profile`. Sign in again and compare the new session identifier with the one you saw earlier. Record whether it changed and leave the identifier values out of your report.

**Expected result.** The browser holds a session identifier, and the server reads the role from the account record. Opening the profile after sign-out returns you to sign-in. Signing in again gives you a new session identifier.

### 6 · SR-06 — Failed logins keep receiving fresh counters

1. In `app.js`, find `loginLimit`. Write down the configured limit and time window. Check what `skipSuccessfulRequests` means for a successful sign-in.
2. Find the `trust proxy` setting in the same file. Use it to explain where Express gets the client address in this local setup.
3. Draft a check that a normal sign-in succeeds below the limit and that a client exceeding the failure limit receives a throttled response. Use your successful sign-in from SR-03 as the live example and the casebook trace as the evidence to explain.

**Expected result.** The limit is **12 failed attempts per 10 minutes**, and a throttled response uses HTTP **429**. Record the throttled response as an expected result from code review.

### 7 · SR-07 — A download appears outside the course catalog

1. Sign in as **Amina** and open **Course catalog**.
2. Under **Course resources**, select **Download enrollment guide**. Open the downloaded file and check its title.
3. Open `/resources/unknown-guide` in the browser.

**Expected result.** The published link downloads `enrollment-guide.txt`, titled **Campus Registry enrollment guide**. The unknown identifier shows **Resource unavailable** (HTTP **404**). Explain how the server decides which resource to return.

### 8 · SR-08 — A developer backup appears in the web inventory

1. Open `/assets/portal.css` in the browser. Check that the stylesheet loads.
2. Open `/assets/app.js`. Record what the portal returns.
3. In `app.js`, find the static asset route. Compare its configured directory with the project files on disk.

**Expected result.** The CSS loads from `public/`. The application source is outside that directory, so the second URL shows **Page unavailable** (HTTP **404**). Name one type of development file that should stay outside the public directory.

### 9 · SR-09 — The transcript shows a negative credit total

1. Sign in as **Amina**. Find **Web Application Development** (`CSC 214`) on **Overview** and note the credits.
2. Find the same course under **Course catalog**. Compare the credits in both places.
3. In `app.js`, trace the enrollment handler to the course lookup. In `data.js`, check which table stores credits and which values an enrollment stores.
4. Find the unknown-course check in `test/portal.test.js` and use the command in SR-10 to run it.

**Expected result.** Both pages show **3 credits** for `CSC 214`. Credits are read from the course catalog, and the automated check confirms that an unknown course returns HTTP **400**.

### 10 · SR-10 — One course is counted twice

1. In a second terminal, open the `student-portal` folder and run:

   ```sh
   npm test -- --test-name-pattern="student requests a course and a registrar approves it once"
   ```

2. In `test/portal.test.js`, find the two submissions for **Applied Statistics** (`STA 202`). Note what the test expects for the first submission, the second, and the final number of rows shown on **Overview**.
3. In `data.js`, locate the enrollment constraint. Explain what must still be true if two submissions arrive together.

**Expected result.** The test passes on its own temporary records. The first request is accepted, the second returns HTTP **409**, and the overview contains one row for `STA 202`. The two submissions in the test are sequential. Use the database constraint to explain what happens when they overlap.

### 11 · SR-11 — A request begins in the approved state

1. Sign in as **Amina**. In **Course catalog**, request **Database Systems** (`INF 208`).
2. Open **Overview** and record the initial status.
3. Switch to **registrar**. Approve Amina's `INF 208` request, then check whether it still appears under **Enrollment requests**.
4. Switch back to **Amina** and check the status again.

**Expected result.** The request starts **Pending**. After the registrar approves it, it leaves the review queue and appears as **Approved** on Amina's overview. The test from SR-10 also verifies that an already approved request cannot be approved again. Record the allowed order of actions and the role that performs each one.

### 12 · SR-12 — A password reset is attributed to a different account

1. Read the helpdesk summary in **Security casebook**. Identify the account that a reset should belong to.
2. Draft four acceptance checks for a proposed account recovery feature. Cover a valid reset, an attempt involving the wrong account, an expired reset grant, and reuse after a successful reset.
3. Describe the expected outcome of each check.

**Expected result.** A valid grant allows one reset for its intended account. A grant for a different account, an expired grant, and a previously used grant are each denied. This is a design exercise. Label the four results as expected.

---

## Part B — Complete one enrollment from start to finish

1. Sign in as **Zainab**. Open **My profile** and confirm student number `ST-1003`.
2. Open **Course catalog** and request **Web Application Development** (`CSC 214`). Go to **Overview** and record **Pending**.
3. Switch to **registrar**. In **Student directory**, search for `ST-1003` and confirm the name **Zainab Musa**.
4. In **Enrollment requests**, approve the row for **Zainab Musa** and `CSC 214`.
5. Sign back in as **Zainab**. Confirm that **Overview** shows `CSC 214` with **3 credits** and status **Approved**.

If you have already used this course for Zainab, restart the classroom database before repeating this walkthrough.

## Part C — Defence checklist and submission

Submit one row for each of the 12 cases using this format.

| Case ID | Likely weakness | Affected data or action | Clue from the casebook | Proposed protection | Verification result |
| --- | --- | --- | --- | --- | --- |
| SR-__ | | | | | |

- For each row, include a valid outcome and a rejected or safely handled outcome. Mark results as **Observed in browser**, **Verified by test**, or **Expected from review**.
- For Part B, list the accounts you used and the two statuses you observed for Zainab.
- Choose two cases for a deeper explanation. Name the specific code or database constraint that enforces the rule. Describe one regression check for each case and the outcome it should assert.
- To run the full verification suite, use `npm test` from the `student-portal` folder.
