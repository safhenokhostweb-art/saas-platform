# Offline grace policy

No schema change is required. `last_validated_at`, `platform_valid`, `expires_at`, and the cached module list are sufficient.

On a transient platform outage, the customer retains the last valid projection only while its cached license remains valid (including expiry and domain checks); the sync service records a connectivity warning and updates retry timing. A definite platform rejection is never granted grace and clears the projection.

