# ASCINDER Theme

ASCINDER Theme is a lightweight classic WordPress theme built for ascinder.com with a cinematic, immersive homepage experience.

## Highlights
- Classic theme architecture (`header.php`, `footer.php`, template files).
- Immersive front page powered by vanilla JavaScript and CSS with scroll-snap panel navigation.
- Polylang-aware EN/AR behavior with a minimal opposite-language toggle.
- Arabic front-end numeric/date localization (Arabic-Indic digits + Levant month names).
- Accessible interaction model (focus-visible styles, keyboard navigation, reduced-motion support).
- No build tooling and no external dependencies.

## Screenshot note
Add `screenshot.png` manually after merge if WordPress admin theme preview imagery is required.


## Deployer-friendly notes
- This theme is deploy-friendly for git-based workflows: commit only source files (`.php`, `.js`, `.css`, `.json`, docs) and avoid generated/binary artifacts in PR automation.
- Keep binary files out of automated PRs (for example media exports or archive files); upload those through WordPress media/admin flows instead.
- If you want WordPress theme preview imagery, add `screenshot.png` manually after merge/release (do not rely on automation to generate/commit it).

