# Issue #47 source matrix

| Requirement / asset | Decision source | Implemented result |
|---|---|---|
| Final homepage order, Dekkhotell copy, four large steps, 700 kr tier, contact/B2B copy | Customer materials consolidated in GitHub Issue #47 and the updated Telegram requirements package | Guarded content manifest plus final Blade composition; operator edits are preserved |
| Norwegian customer copy | Exact text recorded in Issue #47 | Stored in `CustomerHomepageCompositionSeeder`; public NB is the default |
| Russian QA copy | Project QA requirement; not a public language selector | Faithful fields in the same settings model, reachable only through the existing private `/ru` preview |
| Dekkhotell video | Customer named `003 muzik.mp4`; Telegram export recorded the 95 MB file as omitted because it exceeded the export limit | Video-capable admin field and resilient player are complete; production remains on its poster until the exact optimized file is supplied |
| Dekkhotell and mobile-service posters | Existing approved local EasyTire media | Reused locally; no unrelated stock video substituted |
| Nordic Spas | Official site: <https://www.nordicspas.no/> | Local `nordic-spas.png`, SHA-256 `4687F905C41225262C1CABCE7ECF6023601058F7DE3D62E91FF2E80BC4B14510` |
| Element NOR | Official site: <https://elementnor.no/> | Local `element-nor.png`, SHA-256 `2DB172E6EC06CAC2EEF55499C572EE1F374C614B297B59A9FF73AA8C3771ABA4` |
| Carsoe | Official site: <https://carsoe.com/> | Local `carsoe.png`, SHA-256 `D01DF4F4C4BC56603B12B32E9FDAA2292B9268346387D404917DEF0B0950AD1A` |
| Avis | Official Norwegian site: <https://www.avis.no/> | Local `avis.png`, SHA-256 `F60946E4E0812DD1F1A67894F9E33A78D44244D0392058BB590E318FF594536D` |
| Snapchat | Existing approved storefront setting only | No guessed account or new social network was added |
| Long promotion conditions inherited from Issue #46 | PM carry-over V46-TEXT-01 in Issue #47 | Stable desktop card height; separate scrollable short/details regions keep all text and CTA reachable; legacy records without short copy expose their full body through disclosure |

External partner files were downloaded for local hosting and verified as
decodable PNG assets on 2026-09-12. Public rendering uses no image hotlinks.
