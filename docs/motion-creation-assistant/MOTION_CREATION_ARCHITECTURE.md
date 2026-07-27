# Motion Creation Architecture

```text
Wizard Request → Smart Planner → Asset references → Timeline proposal
       ↓               ↓                 ↓                ↓
Typography analysis  Quality Assistant  Brand tokens  Voice/subtitle plan
                              ↓
                    Human review / future approval
```

The request, plan, quality report, and voice layer are versioned provider-neutral contracts. Plans reference immutable library IDs and never contain authority to edit or publish. Beginner and Expert modes share the same validation pipeline.
