# Yoowii preproduction deployment setup

This setup only redeploys `php` and `nginx`; it deliberately never runs migrations, down -v, prune, or restarts MySQL/Redis.

## One-time privileged setup on the VPS

1. Review `scripts/preprod/deploy-vps.sh` from a trusted commit, then install it as root:
   `install -o root -g root -m 0755 scripts/preprod/deploy-vps.sh /usr/local/sbin/yoowii-deploy-preprod`
   (copy the reviewed file to the VPS first; do not execute a remote pipe to shell).
2. Create a restricted sudoers entry using `visudo -f /etc/sudoers.d/yoowii-preprod-deploy`:
   `deploy-yoowii ALL=(root) NOPASSWD: /usr/local/sbin/yoowii-deploy-preprod *`
   The executable validates its sole argument as a full SHA and is root-owned. Do not grant Docker group access or general sudo.
3. Root must have read access to GHCR images via its existing Docker login.
4. Install the reviewed `compose.preprod.yml` initially; after bootstrap, **every deployment transfers the Compose file from the same Git commit as the image pair**, validates it against the existing VPS `.env`, and atomically swaps it with the application images. Never commit or transmit `.env`.
5. **Before enabling this workflow**, upgrade `/usr/local/sbin/yoowii-deploy-preprod` using the reviewed `scripts/preprod/deploy-vps.sh` on this branch. The new script requires the pinned Compose via stdin. Do not merge until the server-side script is installed, or the existing workflow will fail.
6. Place Realisaprint credentials only in `/opt/yoowii/preprod/.env` (mode 600); never in GitHub logs or files. Check that Compose passes the variables through to PHP.
7. Confirm `YOOWII_IMAGE_TAG` points to an already-deployed immutable `sha-...` image.
8. Confirm restricted SSH login works with GitHub Environment `preprod` secrets `PREPROD_SSH_KEY`, `PREPROD_SSH_HOST_KEY` and vars `PREPROD_HOST`, `PREPROD_USER`.
9. Perform a manual workflow dispatch with an existing successful image SHA and check HTTPS through Plesk (HTTP Basic requires an authorized tester).

## Promotion rules

Build workflow creates two images from `chore/ci-cd`. The build workflow itself runs a deployment job after successful image publication. A separate manual workflow handles explicit SHA redeployment. `preprod` environment may require reviewers/approval. A manual workflow dispatch redeploys an explicit immutable SHA.

The workflow checks out the GitHub commit (or fetches the exact requested commit for manual dispatch) and pipes `compose.preprod.yml` over the restricted SSH command. The server script validates the received Compose against the existing `.env` *before* replacing the active file, pulls both immutable images, swaps the Compose, recreates php/nginx only, checks the local storefront, and persists the image tag on success. On failure after the swap, it restores the previous Compose **and** image tag on running containers. The `.env`, MySQL, Redis and persistent volumes are not changed by this workflow. Rollback does **not** cover DB/schema migration (none is run). Make a fresh backup before any manually approved schema change.

## First rollout (manual, before merge)

1. Review the new script in the PR. Save an independent backup of the currently installed executable: `cp -p /usr/local/sbin/yoowii-deploy-preprod /usr/local/sbin/yoowii-deploy-preprod.bak-issue3`.
2. Transfer the **reviewed** `scripts/preprod/deploy-vps.sh` from the feature branch to a temporary file on the VPS (do not pipe directly into a shell), check it with `bash -n`, then install with `install -o root -g root -m 0755 ... /usr/local/sbin/yoowii-deploy-preprod`.
3. Confirm existing `.env`, live Compose, backup and application health before merging. Run a single manual staging deployment using the new protocol and check that both image tag and Compose match the selected SHA.
4. To back out before any deployment, restore the saved executable. If a new workflow is active, revert the workflow change before restoring the older single-argument script.

## Manual recovery

If deployment or rollback fails, stop new workflow runs, inspect `docker compose ps` and application logs; restore the previous reviewed Compose from backup and the previous image tag. Never use `docker compose down -v` or manually delete a named volume. Preserve the original `.env` and backups. The rollback is best effort; an operator must investigate a failed healthcheck.

Do not reuse this pipeline for production without separate environment, paths, credentials, release promotion, and manual approvals. The application code on `feat/yoowii-tailwind-theme` must be merged/validated for the build branch before promotion.
