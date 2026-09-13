# Math REST API specification

`math-v0.json` describes the endpoints in `includes/Rest/`. `sandbox.html` serves
it through Swagger UI so the endpoints can be browsed and tried.

## Where the specification comes from

It was adapted **by hand** from RESTBase, not generated. These are the sources,
pinned by content so they stay readable if the files move or the repository goes
away. Resolve one at `https://archive.softwareheritage.org/<swhid>`.

| RESTBase file | SWHID | what was taken |
|---|---|---|
| `v1/mathoid.yaml` | `swh:1:cnt:7794702d70b6cd023b4864a134abbb8829abf1e8` | the three operations, their summaries and descriptions, parameter and response shapes |
| `sys/mathoid.js` | `swh:1:cnt:8361e6293f9cb0af2ed5dae3a949a65beefab49e` | that `check` answers with `x-resource-location`, which the other endpoints take as their hash |
| `sys/post_data.js` | `swh:1:cnt:37b1271b57efe5de321bc3a38f93d747d54f2dca` | the address rule, `sha1` over `fast-json-stable-stringify` output, lines 9-14 |

What deliberately differs from the source:

* paths are `/math/v0/…` rather than `/media/math/…`, since this is served by
  `rest.php` and versioned separately
* `png` is not offered; RESTBase rewrites it to `svg` anyway
* `servers` and `security` are declared, which RESTBase's fragment leaves to the
  document that includes it
* each operation has an `operationId`, so Swagger UI can deep-link to it
* for `chem`, `checked` is the expanded form rather than `{\ce {...}}`, because
  Math expands mhchem while checking rather than while rendering (T348975, Math
  commit 5cce2b45, building on T340023). The address is the sha1 of whatever
  `checked` holds, so it differs from the live one for chemistry and matches it
  for everything else.

To check the spec against its source, fetch the archived `mathoid.yaml` and
compare the operations by hand:

    curl -s https://archive.softwareheritage.org/api/1/content/\
    sha1_git:7794702d70b6cd023b4864a134abbb8829abf1e8/raw/ | less

## Keeping it honest

Lint before committing a change to it:

    npx @redocly/cli lint specs/math-v0.json

The addresses the endpoints mint are asserted against the live API in
`tests/phpunit/unit/Rest/FormulaHashTest.php`, so a drift from RESTBase fails
the build rather than being noticed by eye.

## sandbox.html

Hand-written, and short enough to read. It loads `swagger-ui-dist` from unpkg and
points it at `math-v0.json` beside it.

It lives here rather than anywhere else for one reason: the extension directory
is served from the wiki's own origin, so "Try it out" reaches `rest.php` without
running into cross-origin rules. Serving the same page from another port or host
would render the spec but fail on every request, because `rest.php` sends no
`Access-Control-Allow-Origin`.

Open it at `/extensions/MathSearch/specs/sandbox.html` on the wiki. To move to a
newer Swagger UI, change the two unpkg URLs; there is nothing to regenerate.

`Special:RestSandbox` does the same job for a spec registered in
`$wgRestSandboxSpecs`, but it comes with the WikimediaCustomizations extension,
which also brings AuthManager hooks and Wikimedia production policy.
