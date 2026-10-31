# License key rotation

License signatures use a fixed RSA/SHA-256 algorithm. Legacy files keep the
original `{data, signature}` shape and are verified only with
`LICENSE_LEGACY_PUBLIC_KEY_PATH`.

New files include a `key_id` and are verified only with
`LICENSE_PUBLIC_KEYS_PATH/{key_id}.pem`. An unknown `key_id` is rejected and
never falls back to the legacy key. The signing key is read from
`LICENSE_PRIVATE_KEY_PATH` and must be provisioned by the deployment secret
manager; it is never stored in this repository.

For a rotation:

1. Provision the new private key outside the repository and its matching
   public key under `LICENSE_PUBLIC_KEYS_PATH`.
2. Set `LICENSE_ACTIVE_KEY_ID` to the new identifier.
3. Issue new licenses with `php artisan license:generate CUSTOMER YYYY-MM-DD`.
4. Keep the legacy public key while existing licenses must remain valid.
5. Remove the legacy public key only after all legacy licenses are expired or
   have been reissued and the change is approved operationally.

The previously exposed private key must be revoked/quarantined and must not be
used for new licenses. History cleanup and remote key revocation are separate
GitHub security actions and are not performed by the application.
