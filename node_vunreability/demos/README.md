# Standalone vulnerability demos (Node.js)

These demos are **self-contained and runnable** without MongoDB or a network connection. The PHP ports show each bug and its fix side by side. The XSS noticeboard uses sample text to demonstrate safe rendering for three common XSS paths.

```bash
node demos/file-exposure.js          # path traversal, LFI→RCE, source disclosure
node demos/broken-authorization.js   # IDOR + privilege escalation (role cookie)
node demos/logic-flaw.js             # negative amount, client-trusted price, coupon reuse
node demos/race-condition.js         # TOCTOU read-modify-write (lost updates)
node demos/rate-limit.js             # X-Forwarded-For key bypass
node demos/dev-file-leak.js          # secrets served from the web root
node demos/xss.js                    # stored, reflected, and DOM-based XSS concepts
```

| Demo | PHP original | Vulnerable primitive | Secure fix |
|------|--------------|----------------------|------------|
| `file-exposure.js` | `file-exposure.php` | `fs.readFileSync(path.join(base, userInput))`; `import()` user input | allowlist, or `realpath` + prefix containment; never `require()` user input |
| `broken-authorization.js` | `broken-authorization.php` | id/role read from request & cookie, no ownership check | identity+role from server session; ownership check on every object |
| `logic-flaw.js` | `logic-flaw.php` | no `amount>0`/balance check; price & discount from the client | validate the rule server-side; price from the server catalogue |
| `race-condition.js` | `race-condition.php` | read → `await` → write with no lock | async mutex, or atomic `$inc` / `balance = balance + n` |
| `rate-limit.js` | `rate-limit.php` | limiter keyed on `X-Forwarded-For` | key on the real socket; per-IP **and** per-account; atomic counters |
| `dev-file-leak.js` | `dev-file-leak.php` | `express.static(root, {dotfiles:'allow'})` over the project | serve a dedicated `public/`; deny dotfiles/backups; secrets out of the tree |

> Why Node still has race conditions despite being single-threaded: the event
> loop runs one callback at a time, but `await` yields it. Concurrent requests
> interleave in the gap between a read and its write — see `race-condition.js`.

## XSS noticeboard in the browser

From the Node lesson folder, run:

```sh
node demos/xss.js --html "Market & <strong>offers</strong>" > /tmp/xss-noticeboard.html
```

Open `/tmp/xss-noticeboard.html` in a browser. The saved flyers and current search term show the angle brackets as text. Edit the draft flyer to see how the browser preview uses `textContent` to display your input.
