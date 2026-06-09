Stardust font files belong in this folder.
==========================================

The Innovate Alabama brand kit specifies:
  - "Stardust Condensed", sans-serif, weight 200  (display / headings)
  - "Stardust Local",     sans-serif, weight 200  (display / headings)
  - "Times New Roman",                weight 400  (body, intentional serif only)

The licensed Stardust web-font files are NOT included in the brand kit zip
(it ships only logo.svg, favicon.png, an empty icons/ folder, a broken
styles.css, and the spec PDF).

ACTION REQUIRED BEFORE LAUNCH
-----------------------------
Obtain the licensed Stardust web fonts and drop them here as:
  public/fonts/StardustCondensed.woff2
  public/fonts/StardustLocal.woff2

The @font-face rules in assets/styles/app.css reference exactly those paths, so
once the files are present, run the asset build (tailwind:build + asset-map:compile,
which happens automatically on `docker build`) and the real type appears — no
code change needed.

UNTIL THEN: the UI renders the approved fallback stack
  "Stardust Condensed", "Stardust Local", system-ui, sans-serif   (headings)
and a system sans-serif for body/UI text. Times New Roman is deliberately NOT
used as the default UI font (only via the .font-serif-body opt-in class).
