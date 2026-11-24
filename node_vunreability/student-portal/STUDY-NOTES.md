# Web Application Security Study Notes

Review these notes before starting Campus Registry. The banking exercises used PHP with MySQL and Node.js with MongoDB. The same questions apply to any web application that handles accounts, private records, or actions such as payments and approvals.

## 1. What the server can trust

The browser sends data in the URL, form, JSON body, headers, and cookies. Treat each of these as input. The server checks the input's type and meaning, establishes the signed-in identity, and decides whether that person can perform the requested action on the requested record.

When tracing a request, ask four questions.

1. **Who is making the request?** Find where the server verifies the credentials or session.
2. **What are they asking to do?** Identify the action and the record it affects.
3. **Which values can they choose?** A shopper can choose what to buy. The server retrieves the official price.
4. **What must stay true?** A balance must stay within its allowed range. A one-use reward may be redeemed once.

## 2. SQL and NoSQL injection

**Injection** happens when input intended as data starts controlling the instructions interpreted by the database. A login is especially sensitive because the account lookup is part of deciding who can sign in.

| Lesson | How the flaw works | Server-side protection |
| --- | --- | --- |
| **SQL injection** | Building a SQL statement by concatenating input can change its structure. `UNION`-based injection can change which rows come back. Error-based injection can reveal data through database errors. | Use prepared statements with bound values. Use an allowlist for any user-selectable sort field. Give the database account only the permissions it needs and return generic error messages to the browser. |
| **NoSQL operator injection** | MongoDB query objects contain operators as well as values. If a name or password arrives as an object and the server inserts it into a query as-is, it can change the comparison. | Require a single string for each credential and reject arrays or objects before querying. Build the filter with fields and operators chosen by the server. |
| **JavaScript in a database query** | MongoDB features such as `$where` can evaluate JavaScript. Incorporating input into an evaluated expression gives that input control over the query's behaviour. | Use ordinary query predicates and avoid evaluating client input. Return only the fields the caller needs. |

For sign-in, look up the account by a validated identifier, then verify the password against its stored hash. Store a salted password hash using a password-specific algorithm such as Argon2id, scrypt, or bcrypt. Keep passwords and hashes out of API responses and logs. A database lookup with input validation still needs a separate check of the caller's permissions.

## 3. Authentication, authorization, and account recovery

**Authentication** establishes identity. **Authorization** decides what that identity may do. A valid session answers who is signed in. The route handling the request still needs to check the action and the target record.

- **Object-level authorization and IDOR.** IDOR stands for insecure direct object reference. A request can contain a valid account number while the caller has no right to view, edit, or transfer funds from that account. Check ownership or an explicitly permitted role for each record access. For a self-service account page, derive the account from the signed-in identity.
- **Function-level authorization and privilege escalation.** A sensitive action such as issuing a refund requires the right role even when the caller can access the application. Check the role on the server for the action itself. Hiding a button in the browser changes what the user sees. The server still has to enforce the permission.
- **Password change and recovery.** Verify the requester before changing a password. Bind a reset grant to a single account and purpose, give it a short lifetime, and consume it as part of the successful reset. A public account identifier only identifies the record.

For a permissions check, make sure the owner or authorised role can complete the action. Then verify that a signed-in user without that permission is denied and that a rejected write leaves the record unchanged.

## 4. Cookies, sessions, JWTs, and rate limiting

**Server-side sessions.** The browser carries a session identifier. The server uses it to find the signed-in account and its trusted permissions. Rotate the identifier on sign-in, set an expiry, and invalidate the session on sign-out. `HttpOnly` blocks direct reads of the cookie by browser scripts. `Secure` restricts it to HTTPS, and `SameSite` limits when browsers send it with requests from another site.

**JSON Web Tokens (JWTs).** A signed JWT carries claims such as an account identifier and expiry. Its contents are normally readable. Use a vetted library to verify the signature with a protected key and an algorithm configured on the server. Reject unsigned tokens when signed tokens are required. Check expiration, the issuer and audience expected by the service, and any applicable validity start time. Use the verified identity when making authorization decisions.

**Rate limiting.** A login limit uses a counter and a time window to slow repeated guessing. Choose the counter keys on the server. Limits by network address and account can cover different guessing patterns. When a service is behind a reverse proxy, only use forwarded address information from the proxy chain you control. A directly connected client can supply arbitrary header values. When the limit is reached, an application can respond with HTTP **429 Too Many Requests**.

## 5. Business rules and workflow state

A **business logic flaw** lets an action violate the application's rules even though the request was understood. Type checks tell you that a value is a number. Business checks tell you whether it is acceptable in this operation.

| Banking example | Rule the server must enforce |
| --- | --- |
| Transfer amount | Require a valid amount greater than zero, enforce the allowed range and precision, and prevent an overdraft where the account policy prohibits one. Subtracting a negative amount would increase the source balance. |
| Checkout price or refund | Retrieve prices from the catalogue and calculate the total on the server. Base a refund on the original purchase and any earlier refunds. |
| Required verification step | Store and verify workflow state on the server. When a flow requires multi-factor authentication (MFA), the sensitive action checks that the required challenge was completed for the relevant session or action. |
| A one-use coupon or reward | Record successful redemption and enforce the one-use rule as part of the operation that grants the benefit. |

A workflow has valid states and transitions. For each transition, identify who can perform it and the state that must already exist. Check both the allowed sequence and the outcome when a step is missing or repeated.

## 6. Race conditions and atomic updates

A **race condition** occurs when the outcome depends on the timing of overlapping operations. A **time-of-check to time-of-use race (TOCTOU)** can happen when a request checks a condition and then acts on it later. A second request may see the same starting state before the first update completes.

- A **lost update** occurs when two operations read the same starting value and one write overwrites the result of the other.
- A **double spend** occurs when overlapping operations both rely on funds that should cover only one of them.
- A **duplicate claim** occurs when a reward intended for one use is granted more than once.

An **atomic** conditional update checks the rule and writes the result as one operation. An inventory update, for example, can reduce the available quantity only if stock remains. Use a database transaction with appropriate locking or isolation when several records must change together. A uniqueness constraint can enforce one record per account and reward. A request identifier can help recognise retries of the same operation.

When reviewing concurrency, state what must be true after both operations finish. With one item available and two otherwise valid purchases, exactly one should succeed and stock should end at zero.

## 7. File handling and secret exposure

| Lesson | What went wrong | Server-side protection |
| --- | --- | --- |
| **Path traversal** | A file request can resolve outside the intended directory when the server lets the client supply path components. | Map a public resource identifier to an approved file. If variable paths are required, resolve the real path and verify that it is inside the allowed directory, accounting for symlinks. |
| **Local file inclusion (LFI)** | A page or module loader includes a local file selected by untrusted input. If the runtime executes the file, the impact can extend to remote code execution (RCE). | Map page names to fixed templates or handlers. Keep untrusted file selection out of template and module loaders. |
| **Development file exposure** | The web server publishes configuration files, backups, logs, or database exports along with browser assets. | Serve a dedicated public directory containing approved assets. Keep secrets and backups outside it. Remove exposed copies, rotate any affected credentials, and review access records. |

Weaknesses can compound. A publicly served backup that contains a signing key can undermine the tokens protected by that key. Keeping the backup private protects the key, and rotating an exposed key restores control over which signatures the service will accept.

## 8. Recall questions before the project

1. Where does a request get its signed-in identity, target record, and permitted action?
2. Why does a MongoDB login need to check the shape of input, and how does a prepared SQL statement protect a query?
3. What should the server verify before accepting a session or a JWT? Which role check still needs to happen?
4. Where should an application get the official price of an item? Where should it remember that a required step is complete?
5. How could two ordinary requests both pass a separate availability check? What database rule or operation protects the result?
6. How would you verify that an approved file can be downloaded and that private files stay outside the public web directory?

For each question, name the rule, the place that should enforce it, and one outcome you would expect when that rule is applied.
