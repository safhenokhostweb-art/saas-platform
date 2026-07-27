# Business Profile Schema

The executable schema is `data/business-profile.schema.json`.

Required fields:

- `name`: customer-facing business name.
- `industry`: one of company, medical, restaurant, ecommerce, education, construction, personal-brand, tourism, finance, technology, or manufacturing.
- `business_type`: a more specific free-text classification such as clinic or industrial-company.
- `audience`: company, startup, professional, or consumer.
- `style`: modern, premium, minimal, traditional, corporate, luxury, or playful.
- `brand_theme`: five safe six-digit hexadecimal colors.
- `required_sections`: at least one supported section type.

Optional `description` can feed the local AI-interface placeholder. It is not transmitted anywhere.

The brand theme maps to:

```text
primary    -> --brand-primary
secondary  -> --brand-secondary
accent     -> --brand-accent
background -> --brand-background
border     -> --brand-border
```

Unknown properties are rejected in the formal schema to keep future integration contracts deliberate and versionable.
