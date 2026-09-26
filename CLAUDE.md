# Working on this repository

These are the owner's standing instructions for any work done here.

## Git
- Commit and push directly to `main`. Do not create branches, and do not open pull requests.
- Commit as `saitmplacement-design <333472845+saitmplacement-design@users.noreply.github.com>`.
- No Claude attribution anywhere: no `Co-Authored-By: Claude…` or `Claude-Session:` lines, and no mention of Claude in commit messages.
- Never commit `.env` or any real password, key or secret.
- A push to `main` runs the "Deploy to Hostinger" workflow (`.github/workflows/deploy.yml`).

## Before pushing
- In `backend/`: `php -d memory_limit=1G vendor/bin/phpunit` must pass.
- After changing `backend/resources/css` or `backend/resources/js`: run `npm run build` in `backend/` and commit the rebuilt `backend/public/theme/app.css` / `app.js` (the deploy does not build assets).
