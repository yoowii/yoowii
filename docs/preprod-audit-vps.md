# Yoowii preprod — secure read-only audit

The manual GitHub Actions audit is already available on the trusted default branch:
[`audit-vps.yml`](https://github.com/yoowii/yoowii/actions/workflows/audit-vps.yml).
The PR #15 branch version-controls the root-owned VPS wrapper:
`scripts/preprod/audit-vps-readonly.sh`.

## Security boundary

- The audit uses the SSH identity `yoowii-audit`, distinct from deployment `deploy-yoowii`.
- `authorized_keys` has `restrict,command="sudo -n /usr/local/sbin/yoowii-audit-readonly"`.
- `sudoers` permits only that executable **with no arguments**.
- The wrapper is root-owned and must not be writable by the audit user.
- Do not add a Docker group membership, general sudo, unrestricted shell, SSH port
  forwarding, file transfer, or secret-printing command.
- Never run `docker compose config` or unformatted `docker inspect` in a public workflow;
  both can reveal environment variables.
- The script inspects the worker's effective Docker configuration in memory; it
  prints only PASS/FAIL, never raw variable values or Docker inspect payload.
- A PASS for queue, consumer command and supplier isolation **does not prove**
  messages are processed, nor verify Symfony handler idempotence.

## Install an updated audit wrapper (manual, administrator-approved)

From a trusted Linux workstation with GitHub CLI authenticated, after reviewing
the exact script and its diff:

```bash
gh api 'repos/yoowii/yoowii/contents/scripts/preprod/audit-vps-readonly.sh?ref=chore/secure-manual-vps-audit-workflow' \
  --jq '.content' | base64 -d > /tmp/yoowii-audit-readonly.sh
bash -n /tmp/yoowii-audit-readonly.sh
less /tmp/yoowii-audit-readonly.sh
# Copy using your established, administrator-authorized SSH account:
scp /tmp/yoowii-audit-readonly.sh ADMIN@VPS:/tmp/yoowii-audit-readonly.sh
```

Then on the VPS, first retain a root-only backup and verify syntax:

```bash
sudo cp -a /usr/local/sbin/yoowii-audit-readonly \
  /usr/local/sbin/yoowii-audit-readonly.previous
sudo bash -n /tmp/yoowii-audit-readonly.sh
sudo install -o root -g root -m 750 \
  /tmp/yoowii-audit-readonly.sh /usr/local/sbin/yoowii-audit-readonly
sudo visudo -cf /etc/sudoers.d/yoowii-audit
sudo /usr/local/sbin/yoowii-audit-readonly
```

Check expected result in the *read-only* report. If the new audit fails or
unexpectedly displays sensitive information, restore the backup (administrator
only) and investigate. Never install the proposed **deployment** script as
part of this operation.

## Interpret results

- Exit `0`: no finding in checked invariants.
- Exit `1`: warnings only; GitHub Actions emits a warning and concludes success.
- Exit `2`: failed invariant; GitHub Actions fails.
- SSH authentication/host verification errors also fail the workflow.

Checks include: root-owned deployment/Compose/overlay files, image tag format,
worker count, container status, image alignment, effective dedicated Doctrine
queue `yoowii_async`, Messenger `async` consumer, disabled mail/supplier
operations, and the existing local HTTP status diagnostic.

## Outstanding checks for the worker-coordinated deploy (PR #14)

- No automatic installation or execution of PR #14 on the VPS.
- Simulated tests and general CI have passed at `930b216d`; this does not
  establish a safe live deployment.
- Verify dedicated queue and runtime isolation with the strengthened audit.
- Plan a supervised deployment/rollback rehearsal covering a message in flight,
  backup/restoration, and PHP/Nginx/worker alignment.
- Keep the `yoowii_async` queue distinct from legacy `default` and supplier
  `yoowii_supplier_async` queues; do not consume or delete messages as part
  of the read-only audit.
