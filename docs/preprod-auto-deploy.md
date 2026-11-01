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
4. Manually install and validate the current `compose.preprod.yml` from the same reviewed branch. This automation **does not synchronize Compose**, so configuration changes and new environment variables must first be installed by an administrator.
5. Place Realisaprint credentials only in `/opt/yoowii/preprod/.env` (mode 600); never in GitHub logs or files. Check that Compose passes the variables through to PHP.
6. Confirm `YOOWII_IMAGE_TAG` points to an already-deployed immutable `sha-...` image.
7. Confirm restricted SSH login works with GitHub Environment `preprod` secrets `PREPROD_SSH_KEY`, `PREPROD_SSH_HOST_KEY` and vars `PREPROD_HOST`, `PREPROD_USER`.
8. Perform a manual workflow dispatch with an existing successful image SHA and check HTTPS through Plesk (HTTP Basic requires an authorized tester).

## Promotion rules

Build workflow creates two images from `chore/ci-cd`. Successful push-triggered builds trigger the deploy workflow. `preprod` environment may require reviewers/approval. A manual workflow dispatch redeploys an explicit immutable SHA.

The script pulls both images, recreates php/nginx only, checks the local storefront, atomically saves the tag on success and attempts rollback to the previous tag after deployment failure. Rollback covers images, **not** DB/schema migration (none is run). Make a fresh backup before any manually approved schema change.

Do not reuse this pipeline for production without separate environment, paths, credentials, release promotion, and manual approvals. The application code on `feat/yoowii-tailwind-theme` must be merged/validated for the build branch before promotion.
