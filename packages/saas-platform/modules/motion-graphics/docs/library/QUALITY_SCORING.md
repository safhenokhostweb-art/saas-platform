# Asset Quality Scoring

Every asset stores four scores from 0 to 100.

## Technical

- 25: valid file/XML and expected package structure
- 20: no unsafe script, event handler, or external dependency
- 20: accessibility metadata
- 20: semantic theme contract
- 15: optimized size, stable viewBox, and valid index path

## Visual

- 30: concept recognition
- 25: category identity
- 20: compositional balance
- 15: consistency with the premium design system
- 10: distinction from other library assets

Visual scoring requires human or approved visual-review evidence. Structural uniqueness alone is not sufficient.

## Reusability

- 25: transparent/responsive composition
- 25: theme adaptation
- 20: usage flexibility
- 15: dependency independence
- 15: stable naming, classification, and package structure

## AI readiness

- 25: complete editable properties
- 25: complete AI editing instructions
- 20: semantic metadata and search fields
- 15: variant/master lineage preparation
- 15: validation and accessibility preservation rules

## Thresholds

| Score band | Meaning |
|---|---|
| 90–100 | Marketplace ready |
| 80–89 | Active with known refinement opportunity |
| 70–79 | Review required |
| Below 70 | Quarantine; do not recommend or publish |

A future quality service may calculate technical fields automatically, but visual score changes require evidence and Platform Admin review. Re-scoring creates audit history; it must not rewrite historical release evidence.

