# A4 · LocalBusiness schema values (for the `dip-studio-seo` plugin)

The live JSON-LD comes from the `dip-studio-seo` plugin (not editable over REST). Change these fields in the
`#organization` node. Values marked `[[OWNER]]` need a real answer first.

| Field | Live value (2026-11-13) | Change to |
| --- | --- | --- |
| `description` | `"DIP Studio"` | `"DIP Studio iz Sarajeva izvodi specijalne efekte (niski dim, hladne prskalice, vatropad, laser), rasvjetu, vatromete i Photobooth / 360 Video Booth za vjenčanja, proslave i poslovne događaje širom Bosne i Hercegovine. Javni cjenovnik: 50–5.000 KM."` |
| `image` | favicon PNG 512×512 | A real event photo ≥ 1200 px wide, e.g. `https://dipstudio.ba/wp-content/uploads/2026/11/dip-niski-dim-prskalice-baloni-prvi-ples.jpg` |
| `openingHoursSpecification` | 00:00–23:59 every day | `[[OWNER]]` real hours for enquiries; must match Google Business Profile |
| `sameAs` | Facebook, Instagram | add `https://www.tiktok.com/@dipstudio.ba`, `https://udajemse.com/oglas/dip-studio`, Google Business Profile URL `[[OWNER]]`, festivo/setly/OLX profile URLs once created (B2, B3) |
| `alternateName` | — | `["DIP Studio Sarajevo", "dipstudio.ba"]` |
| `geo` | — | `{"@type":"GeoCoordinates","latitude":[[OWNER]],"longitude":[[OWNER]]}` (pin of Envera Šehovića bb) |
| `@type` | `LocalBusiness` | `["LocalBusiness","EntertainmentBusiness"]` |
| `areaServed` | 37 cities | keep; make sure the homepage says the same number (A3) |

`BlogPosting.author` (A5): replace the `Person` named "DIP Studio" with the real author once named:
`{"@type":"Person","name":"[[OWNER]]","jobTitle":"[[OWNER]]","url":"https://dipstudio.ba/o-nama/"}`.

Validate after the change: https://search.google.com/test/rich-results and https://validator.schema.org/
