# Authorization Matrix

Authorization is server-side, deny-by-default, site-scoped, and rechecked at request, service/job, publication, export, and delivery boundaries.

| Action | Platform Admin | Template Manager | Designer | Customer Admin | Customer User |
|---|---:|---:|---:|---:|---:|
| Create Asset | yes | no | no | no | no |
| Edit Asset | yes | no | no | no | no |
| Approve Variant | yes | no | no | no | no |
| Publish | yes | no | no | no | no |
| Rollback | yes | no | no | no | no |
| Use Runtime Asset | yes | yes | yes, preview/published only | yes, own site | yes, authorized own-site view |
| Export Package | yes | yes, template references only | no | no | no |

Template Manager can manage template references through the future Template Builder but cannot create/edit Master Assets or publish Motion versions. Designer can submit design intent/proposals only through separately authorized workflows. Customer Admin/User never receive authoring sources or package export authority.

Role possession alone is insufficient: resource ownership, `site_id`, lifecycle, license, contract version, and explicit ability must all pass.

