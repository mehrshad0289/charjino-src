# Torob SKU titles v2

Local title and split-policy fixes for the Charjino Torob v3 feed. This branch does **not** enable any product, does **not** change production `enabled_product_ids`, and must not be deployed until the owner reviews the report.

## What changed

- Gift-card amounts keep decimals (`10.37 AUD` → `10.37` in the title). Thousands with a comma (`1,041 Diamonds`) become `1041`. Ambiguous number formats do not split.
- Top-up titles accept only `N unit` or `N + M unit`. Bonus SKUs such as `80 + 8 CP` keep the bonus in the title.
- Non-numeric SKU names (Weekly Pass, Prime, Level Up, …) do not split unless `sku_name_labels` is filled (default empty).
- PUBG delivery variants (`fast` / `manual` / `reserve`) get a Persian suffix from `topup_variant_suffixes`.
- Valorant, Free Fire, and Mobile Legends take region from the handle remainder only when that remainder is a known region. `my-sg`, `exclusive`, and `special` do not split.
- Handles containing `legacy-of-discord` or `mobile-legends-adventure` are excluded from brand matching.
- Game-key live titles append `game_key_edition_labels` so `global` / `cis` / `row` rows are unique.
- Products with zero orderable SKUs skip with reason `no_orderable_sku` and are not put into the next rollout stage.
- `torob:rollout-plan` and `torob:verify-feed` print exemption reasons with product ids, list `no_orderable_sku` separately, and attach three sample titles per major brand for competitor review.

`fazer-fixes.patch` at the repo root did not apply (`orig/` / `patched/` paths). The same logic was ported onto `app/Services/Torob/TorobFeedCatalogCopy.php` and `config/torob_split_copy.php`.

## Title rules

Split titles are fully Persian except the region code in parentheses:

`گیفت کارت|شارژ + برند فارسی + کشور + مبلغ + واحد فارسی + (کد ریجن)`

Top-up bonus form: `… 80 + 8 سی‌پی`.

A title is rejected (product does not split) when:

- brand, currency/unit, or required region is unmapped
- the body contains Latin letters
- `.` is not inside a number
- `+` is not between two numbers
- any emit SKU cannot build a unique Persian title

## Config keys (`config/torob_split_copy.php`)

| Key | Role |
|-----|------|
| `brands`, `regions`, `currencies`, `currency_aliases`, `unit_aliases`, `brand_units` | existing maps |
| `regions.kh` | کامبوج |
| `region_aliases` | brazil, indonesia, malaysia, philippines, singapore, turkey, united-states |
| `sku_name_labels` | default `[]` |
| `brand_excluded_handle_fragments` | `legacy-of-discord`, `mobile-legends-adventure` |
| `topup_region_handle_brands` | valorant, free-fire, mobile-legends |
| `topup_variant_suffixes` | fast/manual/reserve Persian phrases |
| `game_key_edition_labels` | global/row/cis/… |

`config/torob.php` `enabled_product_ids`, `enabled_group_slugs`, and `exclude_product_ids` are unchanged.

## Caps (unchanged)

- Global feed ≤ 1.5 × stored baseline (50,034 → 75,051)
- Each stage ≤ 500 products and ≤ 1,500 extra rows
- Products with more than 30 SKUs stay exempt (`over_max`)

Never enable steam-gifts, Manual Services, or Telegram Premium `55813`.

## Rollback

On this branch only: revert the commit (`git revert` / `git reset` on the local branch). Production runtime, canonical JSON, Redis `torob:v3:*`, and the live 52-product enable list are untouched because this work was not deployed.

## Owner decisions (required before enable)

1. Persian phrases «تحویل سریع / دستی / رزرو», «نسخه …», and «کامبوج»
2. Persian labels for Weekly Pass / Level Up / Prime / First Top-Up Bonus (`sku_name_labels`)
3. Region handling for `mobile-legends-exclusive`, `mobile-legends-special`, `free-fire-my-sg`
4. Unique titles for steam-gifts (out of scope here)
5. Fazercards sync migrations on the server (`fazercards_sync_checkpoints`, `steam_gift_appid`) — production change, separate confirmation
6. Whether to run the later 197-product gift-card rollout after this is deployed and titles are reviewed
