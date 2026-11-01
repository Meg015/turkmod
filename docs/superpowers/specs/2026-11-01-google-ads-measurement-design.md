# Google Ads measurement repair

The user authorized testing and applying launch preparation fixes, with no balance payment or campaign activation. Preserve the existing conversion action `6728644976` and its destination `AW-753258530/7ALTCPDyu4gZEKKgl-cC`.

The active public renderer ignores the saved Google tag ID. Emit the configured tag once using the existing external initializer. Read the optional conversion destination from `GOOGLE_ADS_CONVERSION_SEND_TO` in the existing environment configuration, validate that it matches the configured AW tag, and send the existing pageview conversion only on the homepage. Invalid or absent configuration must not emit a conversion.

Allow only Google's documented tag script origins and tag frame origin in the active and fallback CSP. Preserve nonce protection and all other settings. Do not change the theme template or deploy unrelated local changes.

Verify generated markup, destination validation, homepage scoping, initializer queue behavior, PHP syntax, and live browser requests. Back up production files outside the public webroot; stage from production snapshots and abort if source changed before deployment. Campaign remains paused with 19.50 TL average/day, which corresponds to Google's standard 39 TL daily billed ad cost limit. The remaining margin toward the user's 50 TL target is reserved for Turkey surcharges and applicable taxes; this is not a guaranteed tax-inclusive billing cap. Because the highest budget selected in a day determines that day's limit, activation should be on 2 November 2026 or later.
