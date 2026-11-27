# Bank App Project

Build a classroom banking app where customers can open an account, fund it through Paystack in Test Mode, and transfer money to other customers in the app. Customers should be able to see their balance and transaction history. Administrators should be able to view customer accounts and update basic customer details.

Use plain PHP, MySQLi, MySQL or MariaDB, HTML, and Bootstrap or your own CSS. Run the project locally with XAMPP. Use fictional customer details and Paystack test payments throughout the project.

## Classwork to revisit

- Clean and validate inputs with string functions and conditionals. Use functions for repeated work and loops to display query results. [PHP exercises](task.md) · [PHP and HTML examples](index.php)
- Process forms with `$_POST`, read page parameters with `$_GET`, and keep the signed-in user in `$_SESSION`. Reuse a navigation component across pages. [Sign-up form](signup.php) · [Form handler](processForm.php) · [Login example](login.php) · [Navigation component](components/navbar.html)
- Connect to the database with MySQLi. Use prepared statements for queries that take values from forms, URLs, or the session. [Database connection](connectdb.php) · [Prepared statement example](login.php)
- Protect administrator pages with a role check and use a record ID to look up a customer. [Admin page](admin.php) · [Role-check example](is_admin.php) · [View example](view-user.php) · [Edit example](edit-user.php)
- Use `$_FILES` and `move_uploaded_file()` for profile photos. Use Composer and Carbon to format transaction dates and calculate the start of a date range. [Upload example](fileUpload.php) · [Date example](datetime.php) · [Composer setup](composer.json)
- Send HTTP requests with PHP cURL, check the response, and read JSON with `json_decode()`. [cURL example](curl.php) · [Payment initialization demo](paystack.php) · [Payment verification demo](verify.php)

The class files demonstrate individual concepts. Use the requirements below to finish the flows that the examples start.

## 1. Set up the project and database

1. Create a `bank-app/` folder for your implementation. Start Apache and MySQL in XAMPP. If you put the folder inside the class directory under `htdocs`, the starting URL is `http://localhost/oct-2026/bank-app/` on the default Apache port.
2. Create a database for the app and include a `database.sql` file that can set it up from scratch. Share the MySQLi connection across pages using an include. Run `composer install` from the folder containing your project's `composer.json` and load the Composer autoloader where you use Carbon.
3. Use the following data model as a guide. You can choose different table and column names if you preserve the relationships and rules.

| Record | Fields to store | Rules |
| --- | --- | --- |
| `users` | ID, first name, last name, email, password hash, role, optional photo path, creation time | Email is unique. Roles are `customer` and `admin`. |
| `accounts` | ID, user ID, account number, balance in kobo, creation time | Each customer has one account. Account numbers are unique strings of 10 digits so leading zeroes are preserved. New balances are zero. |
| `transactions` | ID, unique reference, type, amount in kobo, source account ID, destination account ID, status, optional narration, creation time | A deposit has a destination and an empty source. A transfer has both accounts. Use statuses such as `pending`, `successful`, and `failed`. |

Use whole kobo for storage and calculations. For example, ₦1,250.50 is `125050` kobo. Customer-facing pages should display naira with two decimal places. Accept only positive amounts entered as ordinary naira values with up to two decimal places. Reject blanks, zero, negative numbers, text, and values such as `12.345`.

## 2. Register, sign in, and sign out

### Create an account

- Ask for first name, last name, email, password, and password confirmation. Process the form with `POST` and validate every field in PHP.
- Trim the names and require both. Require at least three characters for the first name. Trim the email, lowercase it, validate its format, and reject an email that is already registered.
- Require at least eight password characters, including an uppercase letter, a lowercase letter, a digit, and a special character. Check that both password fields match. Store the result of `password_hash()` in the database.
- Assign the `customer` role on the server. Generate a 10-digit account number and ensure it is unique. Create the user and their zero-balance account together so a failure during sign-up leaves neither record behind. On success, send the customer to the login page.

### Start and end a session

- Look up the user by email with a prepared statement and check the submitted password with `password_verify()`. Use a single message such as “Invalid email or password” when sign-in fails.
- On successful sign-in, regenerate the session ID and store the user ID in `$_SESSION`. Send customers to their dashboard and administrators to the admin area. Determine page access on the server using the signed-in user's role.
- Set the session to expire 15 minutes after sign-in. Use `time()` to check the expiry on protected pages and redirect expired sessions to the login page with a message. Submit the Logout button with `POST` and end the session immediately. End PHP execution after a redirect.

## 3. Build the customer dashboard

- Display the customer's name, account number, current balance, and five most recent transactions. Load these from the database for the signed-in customer. Show a clear empty state for a new account.
- Include links to Fund Account, Transfer, Transaction History, Profile, and Logout. Use a shared navigation component with links appropriate to the signed-in role.
- Make forms and tables usable on a phone and a desktop. Give fields clear labels and show success and error messages where the customer can see them. Use `htmlspecialchars()` when displaying customer-entered text in HTML, including names and transfer narrations.

## 4. Fund an account through Paystack

1. Show a form that asks for an amount in naira. When the signed-in customer submits it, validate the amount, convert it to kobo, generate a unique reference, and store a pending deposit for their account. Use the customer's email from the database.
2. With PHP cURL, send `POST /transaction/initialize` to `https://api.paystack.co`. Send the email, amount in kobo, `NGN` currency, reference, and full callback URL as JSON. The URL should point to your verification page and be reachable by the customer's browser. Authenticate the request with a Paystack test secret from local configuration. Keep the secret in server-side configuration and out of the submitted repository. When initialization succeeds, redirect the customer to Paystack's `authorization_url`. [Paystack initialization reference](https://paystack.com/docs/api/transaction/#initialize)
3. On return, read the reference from `$_GET`. Find the deposit by reference and confirm that it belongs to the signed-in customer's account. Look up the payment from PHP using `GET /transaction/verify/{reference}`. Compare the verified reference, amount in kobo, and currency with the deposit you saved. Credit the balance only when the verification response confirms `data.status` is `success` and the values match. [Paystack verification reference](https://paystack.com/docs/api/transaction/#verify) · [Paystack verification guide](https://paystack.com/docs/payments/verify-payments/)
4. Credit the account and mark the deposit successful in one database transaction. A reference can credit the balance only once, even if the return page is refreshed or two requests try to verify it at the same time. If it already succeeded, show the existing result. Keep a way for the customer to recheck their own pending deposit from their history if the callback was interrupted or their session expired.
5. Give the customer a clear result when a payment fails, is cancelled, or cannot yet be verified. Leave the balance unchanged in each case. Keep a deposit pending when its final result is unknown, so that it can be checked again. Handle HTTP errors, cURL errors, and unreadable JSON with a user-facing message.

Complete the flow with Paystack Test Mode when you have a test key. If test credentials are unavailable, use an instructor-approved local mock service that returns sample JSON for initialization and verification. Exercise the same cURL requests and database checks in either setup.

## 5. Transfer money between customers

1. Ask for the recipient's 10-digit account number, an amount in naira, and an optional narration. Submit the form with `POST` and identify the sender's account from the session.
2. Reject an invalid or unknown account number, a transfer to the sender's own account, an invalid amount, or an amount greater than the available balance. A transfer for the full available balance is valid.
3. Debit the sender, credit the recipient, and save the successful transfer under one unique reference. Treat these three writes as one database transaction. If any write fails, roll back the entire operation. Check that enough money is still available when the debit is applied so overlapping transfers cannot overspend the balance.
4. Redirect to a result page or dashboard after the transfer and show the reference and amount. Refreshing the result page should leave both balances intact.

Use InnoDB tables for the multi-step writes in this project. In MySQLi, `begin_transaction()`, `commit()`, and `rollback()` let you group the relevant writes. This applies to creating a user and account, posting a verified deposit, and posting a transfer.

## 6. Show transaction history

- List transactions for the signed-in customer's account from newest to oldest. Show the reference, date and time, direction, amount, and status. Display the narration when present. Label successful transfers “Sent” for the sender and “Received” for the recipient.
- Provide “All transactions” and “Last 7 days” views. Use Carbon to calculate the start of the seven-day window and format timestamps consistently in Lagos time (`Africa/Lagos`). Show an empty state when a filter finds no results.
- A transaction is visible to a customer only when their account is its source or destination. Apply that same check to any transaction detail or payment-status page that accepts a reference in the URL.

## 7. Add a customer profile and photo upload

- Show the customer's name, email, account number, and profile photo. Let the customer edit their first and last names using the sign-up name rules. Use the signed-in user's ID for updates.
- Use a form with `enctype="multipart/form-data"` for the photo. Accept PNG and JPEG images up to 2,000,000 bytes. Check the upload error, file size, and actual file type on the server before saving it.
- Generate a new filename for each accepted upload. Move the file with `move_uploaded_file()`, save its path in the database, and show the new photo. Show a default photo if the customer has never uploaded one. If the upload fails or is rejected, keep the current photo and display an error.

## 8. Build the administrator area

- Create a repeatable way to set up a demo administrator and explain it in your README. Public sign-up creates customers only.
- List customer names, emails, account numbers, and balances in an admin table. Add links to view a customer's details and edit their first name, last name, or email.
- Read the customer ID from the URL for view and edit pages. Validate the ID and show a clear message when the customer cannot be found. Use `POST` to save edits and apply the same name and email validation and uniqueness rules as sign-up.
- Check the admin role on the list, view, edit, and update routes. A customer who types an admin URL or submits an edit request directly must be denied access.

## Test your app before submitting

Create two new customers named Ada and Tunde and set up one demo administrator. Start with a fresh database so you can check the balances below.

| Test | Expected result |
| --- | --- |
| Sign up Ada and Tunde. Try to register Ada's email again with different letter casing and surrounding spaces. Try a weak password or mismatched confirmation. | Ada and Tunde each get a different 10-digit account number and a ₦0.00 balance. The invalid sign-ups create no extra users or accounts. |
| Try a wrong password, then sign in correctly, log out, and visit a protected page directly. Also test session expiry. | A wrong password gives a clear error. Logout and expiry both require a fresh sign-in. For the demo, you may temporarily shorten the expiry and restore it afterward. |
| Fund Ada with ₦5,000.50. Refresh the payment return page and recheck the same reference. | Ada has exactly ₦5,000.50 after each check. The deposit appears as successful only once in her history. |
| Try an unverified or cancelled deposit, an unknown reference, and a known reference while signed in to the wrong customer account. | No balance changes. The wrong customer cannot view the deposit. If using a mock, also verify that a success response with a mismatched amount leaves the balance untouched. |
| Transfer ₦1,250.25 from Ada to Tunde. Refresh the result page and sign in as Tunde to check his dashboard. | Ada has ₦3,750.25 and Tunde has ₦1,250.25. Both can see the same transfer reference, with “Sent” for Ada and “Received” for Tunde. |
| Try sending more than the balance, sending to the same account, or sending to an unknown account. Also try `0`, `-50`, and `12.345` as amounts. | The app rejects the requests. Neither balance changes and no successful transfer appears. |
| Check each customer's history and switch between the two filters. In your demo database, set the completed transfer's timestamp to eight days ago. | The deposit is visible only to Ada. The older transfer appears in “All transactions” and is excluded from “Last 7 days.” |
| Transfer Ada's remaining ₦3,750.25 to Tunde. | The transfer succeeds. Ada has ₦0.00 and Tunde has ₦5,000.50. |
| Upload a valid profile photo, then try an oversized image and a non-image file. | The valid photo is saved and displayed. Both invalid files are rejected and the valid photo is still displayed. |
| Open the admin list, view a customer, and save a valid edit as an administrator. Repeat while signed in as a customer, including a direct edit request. | The administrator can view and edit customer details. The customer is denied access and the record is unchanged. |

## What to submit

- Your `bank-app/` source folder, including PHP pages, shared components, assets, and `composer.json`.
- `database.sql` and a `README.md` with XAMPP setup steps, Composer setup, the local URL, how to create the demo administrator, and how to configure test payments. Provide example configuration with placeholders for secrets.
- Three screenshots showing the dashboard after funding, the completed transfer in transaction history, and the admin customer list. Use only your demo data.
- A short checklist in the README showing which tests you ran and their results. Be ready to demonstrate sign-up, funding, transferring, checking both balances, and denying a customer access to the admin area.

## Marking guide

| Area | Marks |
| --- | ---: |
| Registration, validation, sign-in, and session protection | 20 |
| Database setup, account numbers, and dashboard | 15 |
| Paystack initialization, verification, and protection against duplicate credits | 20 |
| Transfers, balance checks, and consistent updates | 20 |
| Transaction history and Carbon date filter | 10 |
| Customer profile and photo upload | 5 |
| Admin pages and role protection | 5 |
| Setup instructions, testing, and usability | 5 |
| **Total** | **100** |
