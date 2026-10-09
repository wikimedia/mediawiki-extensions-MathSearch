# Formula hash formats

Every hash format that Math and MathSearch have used for a formula, sorted by
the date it first appeared. Dates are author dates; commits are in Math (M) or
MathSearch (MS) on `master`. "Derivable" lists the other rows that can be
computed from a value of that row alone, without the input. A hash cannot be
turned into one over a different preimage, so only re-encodings and identical
preimages are derivable.

| # | From | Until | Repo | Preimage | Encoding | Used for | Derivable |
|---|---|---|---|---|---|---|---|
| 1 | 2011-04-09 M `c4d93497` | 2023-11-19 M `9236575a` | M | `md5(tex)` | 16 bytes binary, `pack( 'H32', … )`, `varbinary(16)` | `math_inputhash` of `math`; of `mathlatexml` and `mathoid` from 2014-05-20 (M `d93fdf91`, `3cbb5225`). From 2014-02-07 (M `ff830485`) `tex` is the user input, not the checked TeX. | 4, 5, 13, 15 by `bin2hex()` |
| 2 | 2011-04-09 M `c4d93497` | 2018-05-19 M `4156f1c9` | M | `md5` of the TeX texvc outputs | 32 hex chars as PNG file name; 16 bytes binary in `math_outputhash` | texvc images | binary ⇄ hex of itself only |
| 3 | 2012-07-26 MS `91c36cd` | 2023-11-19 MS `9b8799e` | MS | as 1 | as 1 | `mathindex_inputhash`; `mathobservation_inputhash` from 2013-01-27 (`3bd518a`); `mathperformance` from 2014-03-03 (`3503353`); `math_wmc_*` from 2015-01-03 (`1b408f8`); `mathlog` from 2014-11-18 (M `54ad7fac`), in MathSearch from 2015-07-12 (`9fde25b`) | as 1 |
| 4 | 2013-09-23 M `61a0c7dc` | 2023-11-19 M `9236575a`; `MathObject::hash2md5` in MS with the commit adding this file | M, MS | as 1 | 32 hex chars: `getMd5()`, `dbHash2md5()`, `hash2md5()` | `newFromMd5()`, ExportCache from 2015-03-16 (MS `6a06d0a`), PNG export paths from 2016-04-20 (MS `9cac4f3`) | 1, 3 by `hex2bin()`; 5, 13, 15 are the same value |
| 5 | 2014-01-02 MS `b0a0e1c` | 2021-05-07 MS `f5a6015` | MS | as 1 | `CHAR(32)` hex | DB2 export, `math_md5` | as 4 |
| 6 | 2015-11-26 M `6ef2e564` | — | M | `sha1` of the stable JSON `{"q":…,"type":…}`, computed by RESTBase | 40 hex chars, header `x-resource-location` | Mathoid/RESTBase SVG and MathML URLs | 14 is the same value, except for `chem`, where 14 hashes the expanded input |
| 7 | 2020-05-12 M `707ad585` | 2021-02-02 M `77804245` | M | `sha1( VERSION-type-tex )` | hex inside a WAN cache key | Mathoid checker cache | none |
| 8 | 2021-02-02 M `77804245` | — | M | `md5( type-tex )` | hex inside a WAN cache key | checker cache; `LocalChecker` from 2023-06-19 (M `16d1fdac`) | none |
| 9 | 2021-01-23 MS `b78ddd7` | — | MS | `sha1( VERSION-DG-context )`, `sha1( VERSION-F-graph.tex )` | hex inside a WAN cache key | LaTeXTranslator cache | none |
| 10 | 2023-11-19 M `9236575a` | — in M; in MS the commit adding this file | M, MS | `md5( mode . tex . implode( params ) )` | 32 hex chars; `varbinary(32)` in `mathlog`, `mathindex` (MS `9b8799e`), from 2025-06-03 in `mathobservation`, `mathperformance` (MS `7f73154`); cut to 16 bytes in `math_wmc_ref`, `math_wmc_assessed_formula` | `MathRenderer::getInputHash()`, renderer cache keys, `RendererFactory::getFromHash()` | a 16-byte binary form by `hex2bin()`, used nowhere |
| 11 | 2025-06-28 MS `4c80eb4` | the commit adding this file | MS | `md5( harvest )` | `<hex>.xml` | fallback BaseX document name; unreachable from 2025-07-24 (`dbfa3ef`) | none |
| 12 | 2026-08-31 MS `09a18f9` | 2026-09-08 MS `30b319d` | MS | `sha1( input )`, then `-` and 8 hex chars of `sha1` of the JSON params | hex | visual diff test ids | none |
| 13 | 2026-08-31 M `7a5e9834` | — | M | `md5( input )`, `MathReferenceData::HASH_ALGORITHM` | 32 hex chars | `reference.json` keys; visual diff from 2026-09-08 (MS `30b319d`) | 1, 3 by `hex2bin()`; 4, 5, 15 are the same value |
| 14 | 2026-09-13 MS `94ab1c8` | — | MS | as 6, computed by `FormulaHash` | 40 hex chars, `math_rest_hash varbinary(40)` | local RESTBase interface | 6, see there |
| 15 | 2026-10-07 MS `8fc706d` | 2026-10-09 MS `f2a363b` | MS | `md5( input )` | 32 hex chars | YAML of long inputs in UpdateMath | as 13 |
| 16 | the commit adding this file | — | MS | `md5` of the JSON `{"attributes":…,"q":…,"type":…}`: the input and type as MathMathML sends them to RESTBase, `attributes` with the names and values of the tag except `id`, sorted by name, only if there are any; Math sets the id only on the HTML, so Math can use the same hash | 16 bytes binary, `binary(16)` in `mathlog`, `mathindex`, `mathobservation`, `mathperformance`; fits `varbinary(16)` in `math_wmc_*`; hex by `bin2hex()` in ExportCache, FormulaInfo, SpecialUploadResult and BaseX document names | `MathObject::hashInput()` | none |

Not listed, as they never identify a formula: the BaseX login hash
`md5( md5( pw ) . ts )` (MS `604e5be` to `20f0a42`), a `sha256` comparison of
files in Math tests (M `6be57247`) and the SVG ETag `sha1( body )` of the local
RESTBase interface (MS `94ab1c8`).
