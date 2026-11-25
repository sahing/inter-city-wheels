# Firebase Authentication — diagnosis and fixes

The app signs users in with Firebase (Google popup) and then verifies the ID
token server-side. Most sign-in failures are project configuration, not code;
`php artisan firebase:check` reproduces them without a browser.

## One command

```bash
php artisan firebase:check
```

It calls Google with the configured API key and reports:

| Check | What it means |
|---|---|
| Key belongs to project | The API key and `FIREBASE_PROJECT_ID` agree |
| Authorized domains + per-host OK/FAIL | Whether the host you serve from may run sign-in |
| Google provider enabled / disabled | Whether the provider is switched on at all |
| OAuth client id | Which client the popup will use |
| Redirect URI probe | Whether Google accepts `https://<authDomain>/__/auth/handler` |

It exits non-zero when anything will break sign-in.

## Failure 1 — `redirect_uri_mismatch` (error 400 from Google)

The popup is sent to Google with `redirect_uri=https://<authDomain>/__/auth/handler`.
If that exact URL is not listed on the OAuth client, Google refuses before the
consent screen. `firebase:check` prints the exact URI and client id.

Fix, in the Google Cloud project that owns the client:

1. [APIs & Services → Credentials](https://console.cloud.google.com/apis/credentials)
   → the **Web client** named in the output.
2. **Authorized redirect URIs → Add URI**:
   `https://<authDomain>/__/auth/handler` (copy it from the command output).
3. **Authorized JavaScript origins → Add URI**: `http://localhost:8004` (and
   `http://127.0.0.1:8004` if you use that host).
4. Save, wait a minute, retry in a fresh incognito window.

Do **not** put the app URL (`http://127.0.0.1:8004/`) in Authorized redirect
URIs — the app URL is never the OAuth redirect URI, the handler is.

If the project uses a custom OAuth client that you do not control, clear it in
Firebase → Authentication → **Google → Web SDK configuration** so Firebase falls
back to its own client, which always allows its own handler.

## Failure 2 — `auth/unauthorized-domain`

Firebase only pre-authorizes `localhost` and the project's `firebaseapp.com`
host. Serving from `127.0.0.1` counts as a different host.

- Simplest: open the app at `http://localhost:8004`.
- Otherwise add the host under Firebase → Authentication → **Settings →
  Authorized domains**. Some projects reject bare IPs; a hosts-file alias
  (e.g. `intercity.local`) or a tunnel is then the reliable route.

## Failure 3 — Google says `403: access_denied` / "Access blocked"

That is the OAuth **consent screen**, not Firebase: the app is in *Testing* and
the account is not a test user, or the app is unverified for the requested
scopes.

- Google Cloud → **OAuth consent screen → Test users → add the account**, or
  click **Publish app** (basic `email`/`profile`/`openid` scopes need no review).
- A Workspace account can also be blocked by its admin's app policy.

## Server-side verification

`POST /auth/firebase-login` (`App\Services\AlternateAuthService`) verifies a
supplied `id_token` against Google before trusting anything:

- Firebase Auth RS256 ID tokens (`https://securetoken.google.com/<project>`) are
  verified cryptographically against Google's Secure Token x509 certificates
  (cached per `Cache-Control: max-age`); Google OAuth2 ID tokens fall back to
  Google's `tokeninfo`;
- `iss` must be Google's issuer (or `securetoken.google.com/<project>`);
- `aud` must match `FIREBASE_PROJECT_ID` for Firebase ID tokens (or
  `FIREBASE_CLIENT_ID` when validating a Google OAuth2 token);
- `exp` (plus `iat` / `auth_time` on Firebase JWTs) must be valid within a 30 s
  clock tolerance;
- a present email must be verified;
- the token's `sub`, email, name and picture override the posted values, so a
  caller cannot claim another identity.

Without a token the request is refused unless `FIREBASE_ALLOW_ID_ONLY` is on.
That flag defaults to **true outside production** so the local demo (typing a
Firebase ID) keeps working; in production it defaults to **false**. A verified
Google identity can also never create an `admin` account — admins are created by
admins; the demo keeps its admin button because it runs with the flag on.

Relevant env:

```dotenv
FIREBASE_PROJECT_ID=thunk-earn-from-task
FIREBASE_AUTH_DOMAIN=thunk-earn-from-task.firebaseapp.com
FIREBASE_CLIENT_ID=            # optional: pin the accepted audience
# FIREBASE_ALLOW_ID_ONLY=true  # local demos only
```

## Client behaviour

- The SDK is preloaded on page load, so `signInWithPopup` runs inside the click
  gesture and is not blocked as a stray popup.
- A blocked popup falls back to `signInWithRedirect`, and the role is remembered
  so the return trip completes the same sign-in.
- SDK errors are translated into the fix (`auth/unauthorized-domain`,
  `auth/operation-not-allowed`, `auth/invalid-api-key`, and a
  `redirect_uri_mismatch` hint for Google-side rejections).

## Tests

```bash
php artisan test --filter='FirebaseAuthHardeningTest|AlternateLoginTest'
```

`FirebaseAuthHardeningTest` stubs Google's tokeninfo endpoint and covers a valid
token, foreign audience, pinned client, foreign issuer, expiry, unverified
email, Google rejection, id-only refusal in production, the demo path, and the
admin-escalation block.
