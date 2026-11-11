# Issue #5 — Guardrail for Messenger worker during preproduction deploys

## Scope of this first draft

The current root-owned deployment script is **not automatically updated on the VPS**
when the GitHub repository changes. This proposal adds a fail-closed check in
`scripts/preprod/deploy-vps.sh` that refuses to promote PHP and Nginx if the
Compose-labeled Messenger worker exists, including when stopped. The check
runs before any image pull or changes to containers, Compose or `.env`.

This is **not the final worker lifecycle automation**: it deliberately blocks
deployment rather than silently leave the worker on a stale image. It must
**not** be merged/deployed as the final solution until there is a reviewed,
tested reconciliation path for an already-active worker. In particular,
merely calling `stop worker` is insufficient: a stopped container still
retains the previous image and `restart: unless-stopped` does not guarantee
the desired release after manual actions.

## Required full lifecycle (future changes in this issue)

1. Verify the desired release image, pinned Compose and worker overlay from
   the same immutable commit. Refuse unexpected overlay/network/volume/port,
   DSN mismatch or supplier transport consumption.
2. Under the deployment lock, snapshot current PHP/Nginx and worker state,
   then gracefully stop **only** the worker if running (90-second limit).
3. Promote PHP/Nginx as before without recreating MySQL, Redis or volumes.
4. Recreate exactly one worker with the new PHP image and an explicitly
   reviewed `async` transport; verify the running image and command.
5. Preserve the stopped state if the worker was deliberately stopped before
   deployment; never silently enable it.
6. On failure, roll back PHP/Nginx and worker to a mutually consistent
   release or leave the worker stopped with an actionable error. No retry
   storms, no queue purge, no consuming `default` or supplier queues.
7. Test success, rollback after PHP failure, rollback after worker failure,
   stopped-worker handling, missing/invalid overlay, all with mocked Docker.

The operational state currently observed is a **running** worker on the
preproduction VPS. The version of `/usr/local/sbin/yoowii-deploy-preprod`
must be inventoried and reviewed before any installation of script changes.
This PR does not update that installed script, perform a deployment, or
start/stop any VPS containers.
