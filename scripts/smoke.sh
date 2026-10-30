#!/bin/bash
# Production smoke tests: auth, tenant isolation, super-admin wall, permissions, debug leak,
# ledger balance, idempotent POS sale, public store. Needs the demo tenants (SEED_DEMO) to exist.
#   usage: scripts/smoke.sh [base_url]   (default http://127.0.0.1:8014)
B=${1:-http://127.0.0.1:8014}/api; PASS=0; FAIL=0
ok(){ echo "  ✔ $1"; PASS=$((PASS+1)); }; bad(){ echo "  ✘ $1"; FAIL=$((FAIL+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — expected $3, got $2"; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
login(){ for i in 1 2 3 4 5 6; do t=$(curl -s -X POST $B/auth/login -H 'Content-Type: application/json' -H 'Accept: application/json' -d "{\"email\":\"$1\",\"password\":\"$2\"}" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("token",""))' 2>/dev/null); [ -n "$t" ] && { echo "$t"; return; }; sleep 15; done; }
J='-H Accept:application/json -H Content-Type:application/json'
echo "== health / auth"
check "health" "$(code $B/health)" 200
check "no token → 401" "$(code $B/app/customers $J)" 401
check "wrong password rejected (422)" "$(code -X POST $B/auth/login $J -d '{"email":"demo@rakeez.app","password":"wrong-pass"}')" 422
check "bad route is JSON 404 without stack" "$(curl -s $B/app/nope $J -H "Authorization: Bearer x" | grep -c '"trace"\|"file"')" 0
A=$(login retail@test.app Test@1234); S=$(login sweets_shop@test.app Test@1234); D=$(login demo@rakeez.app demo1234)
[ -n "$A$S$D" ] || { echo "cannot log in demo tenants (SEED_DEMO off?) — skipping tenant tests"; echo "passed=$PASS failed=$FAIL"; exit $FAIL; }
echo "== tenant isolation (A's records requested by B must be 404)"
for path in customers invoices inventory/items pos/sales; do
  id=$(curl -s $B/app/$path $J -H "Authorization: Bearer $D" | python3 -c 'import sys,json;d=json.load(sys.stdin);d=d.get("data",d) if isinstance(d,dict) else d;print(d[0]["id"] if d else "")' 2>/dev/null)
  [ -n "$id" ] && check "cross-tenant $path/$id" "$(code $B/app/$path/$id $J -H "Authorization: Bearer $S")" 404
done
echo "== walls"
check "tenant user → /super/stats 403" "$(code $B/super/stats $J -H "Authorization: Bearer $A")" 403
check "module not enabled → 403 (retail→education)" "$(code $B/app/education/students $J -H "Authorization: Bearer $A")" 403
check "public store unknown handle → 404" "$(code $B/public/store/does-not-exist $J)" 404
echo "== money"
check "trial balance balanced" "$(curl -s "$B/app/accounting/reports/trial-balance" $J -H "Authorization: Bearer $D" | python3 -c 'import sys,json;print(json.load(sys.stdin)["balanced"])')" True
item=$(curl -s "$B/app/inventory/items/lookup?q=" $J -H "Authorization: Bearer $D" | python3 -c 'import sys,json;i=[x for x in json.load(sys.stdin)["items"] if x["track_stock"] and float(x.get("stock") or 0)>2][0];print(i["id"])')
key="smoke-$(date +%s)"; body="{\"payment_method\":\"cash\",\"lines\":[{\"item_id\":$item,\"qty\":1,\"unit_price\":1}]}"
s1=$(curl -s -X POST $B/app/pos/sales $J -H "Authorization: Bearer $D" -H "Idempotency-Key: $key" -d "$body" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("id"))')
s2=$(curl -s -X POST $B/app/pos/sales $J -H "Authorization: Bearer $D" -H "Idempotency-Key: $key" -d "$body" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("id"))')
check "idempotent sale (same key → same sale)" "$s2" "$s1"
check "oversell online rejected (422)" "$(code -X POST $B/app/pos/sales $J -H "Authorization: Bearer $D" -d "{\"payment_method\":\"cash\",\"lines\":[{\"item_id\":$item,\"qty\":999999,\"unit_price\":1}]}")" 422
[ -n "$s1" ] && curl -s -X POST $B/app/pos/sales/$s1/cancel $J -H "Authorization: Bearer $D" -d '{}' >/dev/null && ok "test sale $s1 cancelled (stock restored)"
echo "== password change"
check "change password w/o current → 422" "$(code -X PATCH $B/auth/profile $J -H "Authorization: Bearer $D" -d '{"password":"NewPass123","password_confirmation":"NewPass123"}')" 422
echo "passed=$PASS failed=$FAIL"; exit $FAIL
