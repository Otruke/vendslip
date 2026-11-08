# VendSlip chat commands: complete reference

Every way a message to the VendSlip bot can be written. The same rules apply on Telegram,
WhatsApp, Messenger and Instagram, and in the dashboard's /Slash Composer: the bot and the web
app use the same parser (`Backend/app/Services/Slash/*` and `Frontend/src/lib/slash.ts`).

This is the **full developer reference**. Section 12 is a short card you can copy and send to merchants.

**Contents**

1. [How messages are read](#1-how-messages-are-read)
2. [Writing amounts](#2-writing-amounts)
3. [All fields at a glance](#3-all-fields-at-a-glance)
4. [Items](#4-items)
5. [Discounts](#5-discounts)
6. [The other fields](#6-the-other-fields)
7. [Commands](#7-commands)
8. [Confirming: buttons and number replies](#8-confirming-buttons-and-number-replies)
9. [Linking a chat](#9-linking-a-chat)
10. [What the customer receives](#10-what-the-customer-receives)
11. [Every error message](#11-every-error-message)
12. [Merchant quick card (copy and send)](#12-merchant-quick-card-copy-and-send)

---

## 1. How messages are read

- **A field is `/key = value`.** The `=` is required: `/name Ada` is not read as a field.
- **Upper or lower case**, any spacing: `/NAME=Ada`, `/name = Ada` and `/Name   =   Ada` are the same.
- **One field per message, or many in one message.** Separate them with a new line, a comma or a semicolon:

  ```text
  /name = Ada
  /item = 2x Ankara @ 7500
  ```

  ```text
  /name = Ada, /item = 2x Ankara @ 7500, /shipping = 1500
  ```

  Commas inside a value are kept: `/address = 42 Wuse II, Abuja` stays one address, because a new
  field only starts at the next `/key =`.
- **Fields overwrite; items add up.** Sending `/name = Tunde` after `/name = Ada` changes the name.
  Each `/item` message **adds** items to the draft; use `/remove 2` to take one out.
- **Silent until ready.** The bot says nothing while the draft is incomplete, which saves message
  fees on WhatsApp. When it has a name and at least one priced item (or an `/amount`), it sends
  **one** summary with Confirm / Edit / Cancel. Later changes stay silent until `/preview`.
- **Commands** (`/confirm`, `/help` …) have no `=` and must be the **whole message** (section 7).
- **Unknown keys** are reported: `/colour = red` → "/colour isn't a VendSlip field. Send /help for the list."

## 2. Writing amounts

Anywhere an amount is asked for (prices, `/amount`, `/shipping`, `/discount`, `/paid`, item discounts):

| You write | It means |
|---|---|
| `15000` | 15,000 |
| `15,000` | 15,000 (commas ignored) |
| `15000.50` | 15,000.50 |
| `₦15,000` · `N15000` · `NGN 15000` · `15000 naira` | 15,000 |
| `$20` · `USD 20` · `£20` · `€20` | 20 (the sign is ignored; the slip's currency decides, see `/currency`) |
| `15k` · `15K` | 15,000 |
| `7.5k` | 7,500 |
| `1.2m` · `1.2M` | 1,200,000 |
| `1 500` | 1,500 (spaces ignored) |

Not accepted: words (`fifteen thousand`) or negative amounts. More than 2 decimals are rounded to
the nearest kobo/cent (`15000.555` → 15,000.56).

## 3. All fields at a glance

Every alias in a row works the same.

| Field | Aliases | Example | Notes |
|---|---|---|---|
| Customer name | `/name` `/customer` `/client` `/buyer` | `/name = Ada Obi` | **Required.** Up to 160 characters |
| Items | `/item` `/items` `/product` | `/item = 2x Ankara @ 7500` | Section 4. Adds to the draft |
| Single amount | `/amount` `/total` `/price` | `/amount = 30000` | Instead of items: one line "Goods & services". Ignored if items exist |
| Slip type | `/type` `/doc` | `/type = invoice` | Default is receipt |
| Order discount | `/discount` `/disc` | `/discount = 2000` · `/discount = 10%` | Section 5 |
| Delivery | `/shipping` `/delivery` `/logistics` | `/shipping = 1500` | Added after discount |
| VAT | `/vat` `/tax` | `/vat = 7.5` · `/vat = off` | Section 6 |
| Amount paid | `/paid` `/deposit` | `/paid = 20000` · `/paid = full` | Section 6 |
| Due date | `/due` `/duedate` `/due_date` | `/due = 7d` | Invoices only |
| Phone | `/phone` `/tel` `/mobile` `/whatsapp` | `/phone = 0803 123 4567` | 7–15 digits; spaces and dashes ignored |
| Email | `/email` `/mail` | `/email = ada@mail.com` | Must be a valid email |
| Address | `/address` `/addr` `/location` | `/address = 42 Wuse II, Abuja` | Up to 500 characters |
| Note | `/note` `/notes` `/memo` | `/note = Thanks for your patronage` | Up to 1,000 characters, printed on the slip |
| Template | `/template` | `/template = minimalist` | A template's name or short name |
| Currency | `/currency` `/cur` `/ccy` | `/currency = USD` | This slip only. Section 6 |

The draft is ready when it has a **name** and either **at least one priced item** or an **`/amount`**.

## 4. Items

### 4.1 One item: every accepted shape

| You write | Quantity | Price each |
|---|---|---|
| `2x Ankara @ 7500` | 2 | 7,500 |
| `2 x Ankara @ 7500` | 2 | 7,500 |
| `2X Ankara @ 7500` · `2×Ankara @ 7500` · `2*Ankara @ 7500` | 2 | 7,500 |
| `Ankara x2 @ 7500` | 2 | 7,500 |
| `Ankara @ 7500 x 2` · `Ankara @ 7500 x2` | 2 | 7,500 |
| `3 Ankara @ 7500` | 3 | 7,500 |
| `Ankara @ 7500` | 1 | 7,500 |
| `Ankara - 7500` | 1 | 7,500 |
| `Ankara 7500` | 1 | 7,500 |
| `Ankara @ 7.5k` · `Ankara @ ₦7,500` | 1 | 7,500 |
| `2x Ankara` | 2 | **from your inventory** (see 4.3) |

Rules:
- The **last** `@` separates the name from the price, so names can contain `@` earlier.
- Quantity: 1 to 999,999, whole numbers only.
- Name: up to 500 characters. Leading/trailing `-` and `:` are trimmed.

### 4.2 Several items in one message

Separate items with **`|`**, a **new line**, or **`;`**, not with commas (commas can be part of prices like `28,500`):

```text
/item = 2x Ankara @ 7500 | Gele @ 5000 | 3x Wrapper @ 3000
```

```text
/item = 2x Ankara @ 7500
1x Gele @ 5000
```

```text
/item = 2x Ankara @ 7500; Gele @ 5000
```

Or one `/item` per message: each one adds to the draft. If one item in a list can't be read, the
others are still added and the bot names the one it couldn't read.

### 4.3 Prices from inventory

If an item has **no price** and its name (or SKU code) exactly matches an active product in
**Inventory**, the inventory price is used, the product is linked, and stock moves when the slip is issued:

```text
/item = 2x Leather Sneakers
/item = 2x SNK-001
```

Matching is exact, ignoring upper/lower case. No match → the bot asks for the price.

### 4.4 Showing the normal price ("was")

To show that an item sold below its usual price, give the normal price after the selling price:

| You write | Sold at | Shown crossed out |
|---|---|---|
| `Cap @ 4000 was 5000` | 4,000 | 5,000 |
| `Cap @ 4000 (was 5000)` | 4,000 | 5,000 |
| `Cap @ 4000 list 5000` · `rrp 5000` · `normally 5000` | 4,000 | 5,000 |
| `Cap @ 4000 was: 5000` · `was=5000` | 4,000 | 5,000 |

`was` is the **normal price**; the alternative is to give the **discount** (section 5.1). Use one or the other on an item, not both.

## 5. Discounts

There are two kinds, and they can be used together.

| Kind | Where | Affects |
|---|---|---|
| **Item discount** | at the end of an `/item` | That item's price. The old price is shown crossed out |
| **Order discount** | `/discount = …` | The whole order, after item discounts, before delivery and VAT |

### 5.1 Item discount: every accepted form

Start with the price **before** the discount, then the discount.

**A fixed amount off each one** (the default):

| You write | Result for `2x Ankara @ 7500` |
|---|---|
| `2x Ankara @ 7500 less 500` | 7,000 each, total 14,000 |
| `2x Ankara @ 7500 off 500` | same |
| `2x Ankara @ 7500 disc 500` · `discount 500` | same |
| `2x Ankara @ 7500 less: 500` · `less=500` | same |
| `2x Ankara @ 7500 less 500 each` | same (`each` is optional) |
| `… less 500 per unit` · `per item` · `per one` · `per piece` | same |
| `2x Ankara @ 7500 500 off` | same (number first, then `off`) |
| `2x Ankara @ 7500 500 off each` | same |
| `2x Ankara @ 7.5k less 0.5k` · `less ₦500` | same (any amount format from section 2) |

**A percentage off** (the same whether you say each or total):

| You write | Result for `2x Ankara @ 7500` |
|---|---|
| `2x Ankara @ 7500 less 10%` | 6,750 each |
| `2x Ankara @ 7500 off 10%` · `disc 10%` · `discount 10%` | same |
| `2x Ankara @ 7500 10% off` | same |
| `2x Ankara @ 7500 -10%` | same |
| `2x Ankara @ 7500 less 12.5%` | 6,562.50 each (up to 2 decimals) |

**A fixed amount off the whole line** (all the pieces of that item together). Add one of these words
after the amount: `total`, `all`, `for all`, `on all`, `in total`, `line`.

| You write | Result |
|---|---|
| `2x Wrapper @ 3000 less 1000 total` | 1,000 off the two → 2,500 each, total 5,000 |
| `2x Wrapper @ 3000 less 1000 all` · `for all` · `on all` · `in total` · `line` | same |
| `2x Wrapper @ 3000 1000 off total` | same |
| `4 Wrapper @ 3000 less 2000 for all` | 500 off each → 2,500 each |
| `Wrapper @ 3000 less 1000 total` | quantity 1: same as `less 1000` |

The line discount must **divide evenly** between the pieces. `3x Ankara @ 7500 less 1000 total`
is refused with a suggestion (`less 333.33 each`, or `/discount = 1000` for the whole order). VendSlip
never rounds money silently.

**Rules for item discounts**

- The item must have a price written in the message (`@ 7500`). Inventory prices can't take an item discount yet; use `/discount` for those.
- The discount must be **less than the price** (so 0 < discount < price; percentages above 0% and below 100%).
- Use `was` **or** `less` on one item, not both.
- Words that only *contain* "off" don't count: `Shoulder off top @ 5000` is an item called "Shoulder off top".

### 5.2 Order discount

| You write | Means |
|---|---|
| `/discount = 2000` | 2,000 off the order |
| `/discount = ₦2,000` · `2k` · `N2000` | same |
| `/discount = 10%` | 10% of the subtotal (after item discounts) |
| `/discount = 7.5%` | 7.5% (up to 2 decimals) |
| `/disc = 10%` | same as `/discount` |
| `/discount = 0` | removes the order discount |

Sending `/discount` again replaces the previous one (amount or percentage).

### 5.3 How the total is worked out

```text
Subtotal        = sum of (quantity × price after item discount)
Item discounts  = shown for information; already inside the subtotal
– Order discount
+ Delivery (/shipping)
+ VAT (unless /vat is off or inclusive)
= Total
– Paid (/paid)
= Balance due
```

Worked example:

```text
/name = Ada
/item = 2x Ankara @ 7500 less 500 | Gele @ 5000 less 10% | 2x Wrapper @ 3000 less 1000 total
/discount = 2000
/shipping = 1500
/vat = off
```

| Line | Amount |
|---|---|
| Ankara 2 × 7,000 (was 7,500) | 14,000 |
| Gele 1 × 4,500 (was 5,000) | 4,500 |
| Wrapper 2 × 2,500 (was 3,000) | 5,000 |
| **Subtotal** | **23,500** |
| Item discounts (included) | 2,500 |
| Discount | −2,000 |
| Delivery | +1,500 |
| **Total** | **23,000** |

## 6. The other fields

### Slip type (`/type`, `/doc`)

| Receipt (default) | Invoice |
|---|---|
| `receipt` · `rcp` · `rct` · `r` | `invoice` · `inv` · `bill` · `i` |

Credit notes are made from the dashboard, not the bot.

### VAT (`/vat`, `/tax`)

| You write | Means |
|---|---|
| `/vat = 7.5` · `/vat = 7.5%` | 7.5%, added on top |
| `/vat = 7.5 incl` · `inclusive` · `inc` | 7.5%, already inside the prices |
| `/vat = 7.5 excl` · `exclusive` · `exc` | 7.5%, added on top |
| `/vat = off` · `no` · `none` · `0` · `0%` | no VAT on this slip |
| `/vat = on` · `yes` · `default` | the business's default VAT (Settings) |
| `/vat = incl` | the default rate, inclusive |

### Amount paid (`/paid`, `/deposit`)

| You write | Means |
|---|---|
| `/paid = full` · `all` · `yes` · `paid` | fully paid |
| `/paid = none` · `no` · `nothing` | nothing paid yet |
| `/paid = 20000` · `20k` | 20,000 paid; the rest is the balance |

Receipts default to fully paid; invoices default to nothing paid.

### Due date (`/due`, `/duedate`, `/due_date`), invoices only

| You write | Means |
|---|---|
| `/due = 7` · `7d` · `7 days` · `+7` | 7 days from today |
| `/due = tomorrow` | tomorrow |
| `/due = 2026-11-30` | 30 November 2026 |
| `/due = 30/11/2026` · `30-11-2026` · `30.11.2026` | 30 November 2026 (day first) |

### Currency (`/currency`, `/cur`, `/ccy`), this slip only

| You write | Means |
|---|---|
| `/currency = USD` · `usd` | US dollars (any 3-letter ISO code: NGN, GHS, KES, ZAR, GBP, EUR …) |
| `naira` · `₦` → NGN · `dollar` · `dollars` · `$` → USD · `pound` · `pounds` · `£` → GBP · `euro` · `euros` · `€` → EUR · `cedi` · `cedis` → GHS · `rand` → ZAR | |
| `/currency = default` · `reset` · `off` · `none` | back to the business's currency |

### Template (`/template`)

The template's short name (e.g. `minimalist`, with or without `vendslip-`) or its full name.
Unknown names fall back to the business's default template.

## 7. Commands

A command is the **whole message**, with no `=`. Every alias in a row works the same.

| Command | Aliases | What it does |
|---|---|---|
| `/confirm` | `/yes` `/ok` `/generate` `/send` | Issue the slip |
| `/confirm clean` | `/confirm credit` · `/confirm premium` | Issue it without a watermark, using 1 paid credit (Free plan) |
| `/preview` | `/summary` `/status` | Show the current draft again |
| `/edit` | | Reopen a draft waiting for confirmation; then send only what changes |
| `/remove 2` | `/delete 2` | Remove item number 2 (numbers as in the summary) |
| `/cancel` | `/stop` `/discard` | Cancel the open draft |
| `/clear` | `/reset` `/new` | Clear the draft and start fresh |
| `/help` | `/start` `/commands` | The help message |
| `/credits` | `/balance` | Plan, free slips left this month, paid credits, top-up link |
| `/link CODE` | | Link this chat to a business (section 9) |
| `/unlink` | | Disconnect this chat |

In Telegram groups, `/confirm@YourBot` (with the bot's name) also works.

## 8. Confirming: buttons and number replies

After the summary:

| Channel | How to confirm |
|---|---|
| Telegram | Tap **✅ Confirm & Generate** (or **✨ Clean slip** to use a credit); or type `/confirm` |
| WhatsApp (Meta), Messenger, Instagram | Tap **Confirm**, **Edit** or **Cancel**; or type the command |
| WhatsApp through Twilio without buttons | Reply `1` (confirm), `2` (edit) or `3` (cancel) |

While a summary is waiting, these plain replies also work on every channel:
`1` · `yes` · `y` · `ok` · `confirm` → confirm; `2` · `edit` → edit; `3` · `cancel` · `no` · `n` → cancel.

A slip is issued **once**. Tapping Confirm again, or a duplicate delivery from the chat app, never creates a second slip.

## 9. Linking a chat

1. Dashboard → **Bot channels → Link a chat** shows a code (valid 30 minutes, single use).
2. Send `/link CODE` to the bot, or on Telegram tap **Open in Telegram** (it sends `/start CODE` for you).
3. The bot replies "Linked ✅". From then on that chat creates slips for that business as that team member.

Messages from a chat that isn't linked get the welcome text with linking instructions.

## 10. What the customer receives

When a slip is confirmed, the bot sends:

```text
Receipt RCP-2026-0006 · ₦23,000.00
For Ada
View & download: https://vendslip.com/s/Ab3dE9xQ2
```

- The **PDF** is attached where the chat app allows it (Telegram uploads it directly, so it arrives even when testing locally).
- **One short link** on the web app's own address. It opens a page where the customer sees the slip,
  **downloads the PDF** and, for unpaid invoices, **pays online** (`View & pay:`). The PDF downloads
  from that page without any file or server address being shown.
- The link uses `FRONTEND_URL`. Set it to the public address (e.g. `https://vendslip.com`), not
  `localhost`, or customers can't open it. A shorter domain can be set later with `VENDSLIP_SHARE_URL`.
- The **verification** page (`/verify/…`) is linked from that page and from the QR code on the PDF.
- Codes are 9 characters, can't be guessed, and avoid look-alike characters (no 0/O, 1/l/I).

## 11. Every error message

| The bot says | Why |
|---|---|
| `/colour isn't a VendSlip field. Send /help for the list.` | A `/key =` that isn't in section 3 |
| `Couldn't read item "…". Try: 2x Shoe @ 15000` | The item line doesn't match any shape in 4.1 |
| `Item "…": add the price before the discount, e.g. 2x Shoe @ 15000 less 1000.` | `less`/`off` with no `@ price` |
| `Item "…": the discount must be more than 0% and less than 100%.` | `less 0%`, `less 100%` or more |
| `Item "…": the discount is as much as the price or more.` | e.g. `@ 7500 less 7500` |
| `Item "…": … off 3 items doesn't split evenly. Give the discount on each one (e.g. less 333.33 each), or use /discount = 1000 for the whole order.` | A `total` discount that doesn't divide by the quantity |
| `Item "…": use either "was" (the normal price) or "less" (the discount), not both.` | Both on one item |
| `Couldn't read /discount "…". Try 2000 (an amount off the whole order) or 10%.` | Not an amount or a percentage |
| `Couldn't read /amount "…". Try 15000 or 15k.` · same for `/shipping` | Not an amount |
| `Couldn't read /paid "…". Use an amount or 'full'.` | |
| `Couldn't read /due "…". Try 7d or 2026-11-30.` | |
| `Couldn't read /vat "…". Try 7.5 or off.` | |
| `Couldn't read /currency "…". Use a 3-letter code like NGN, USD or GHS.` | |
| `/type must be receipt or invoice.` | |
| `That phone number doesn't look right: …` | Fewer than 7 or more than 15 digits |
| `That email doesn't look right: …` | |
| `Send /remove 2 to remove item number 2.` | `/remove` without a valid number |
| `Nothing to cancel.` · `No open draft. Start with /name = …` | Command with no draft |
| `That code is invalid or expired. Get a fresh one in Dashboard → Channels.` | Wrong or old link code |

## 12. Merchant quick card (copy and send)

> **Make a receipt in chat**
>
> ```text
> /name = Ada Obi
> /item = 2x Ankara @ 7500 | Gele @ 5k
> ```
> Then tap **Confirm**. That's it.
>
> **More you can add**
> - Invoice instead of receipt: `/type = invoice` and `/due = 7d`
> - Part payment: `/paid = 20000` · fully paid: `/paid = full`
> - Delivery: `/shipping = 1500`
> - VAT: `/vat = 7.5` or `/vat = off`
> - Customer details: `/phone = 0803…` · `/address = …` · `/email = …`
> - A one-off total instead of items: `/amount = 30000`
>
> **Discounts**
> - Off each piece: `/item = 2x Ankara @ 7500 less 500`
> - Percentage off an item: `/item = Gele @ 5000 less 10%`
> - Off all pieces of an item together: `/item = 2x Wrapper @ 3000 less 1000 total`
> - Off the whole order: `/discount = 2000` or `/discount = 10%`
>
> **Useful commands**
> `/preview` see the draft · `/remove 2` remove item 2 · `/edit` change it · `/cancel` start again ·
> `/credits` your balance · `/help` this list
>
> Amounts can be written `7500`, `7,500`, `7.5k` or `₦7,500`. Send everything in one message or one line at a time.
