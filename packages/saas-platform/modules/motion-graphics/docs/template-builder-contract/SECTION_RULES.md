# Section Intelligence Rules

The canonical section IDs are `hero`, `about`, `services`, `features`, `products`, `portfolio`, `testimonials`, `team`, `contact`, `cta`, and `footer`.

| Section | Preferred assets | Hierarchy | Maximum motion intent |
|---|---|---|---|
| Hero | Motion, visual asset, object | Primary | High with reduced-motion fallback |
| About | Visual asset, motion, object | Secondary | Moderate |
| Services | Icon, object, visual asset | Supporting | Subtle |
| Features | Icon, object | Supporting | Subtle |
| Products | Object, visual asset, icon | Primary | Subtle |
| Portfolio | Visual asset, object, motion | Primary | Moderate |
| Testimonials | Icon, visual asset | Tertiary | Subtle |
| Team | Visual asset, icon, object | Secondary | Subtle |
| Contact | Icon, visual asset, object | Supporting | None |
| CTA | Motion, icon, visual asset | Secondary | Moderate |
| Footer | Icon, visual asset | Tertiary | None |

Full position and responsive behavior are machine-readable in `contracts/section-intelligence.json`. A proposed asset type or position outside those rules is incompatible even if its industry tags match.
