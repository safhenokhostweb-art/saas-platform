# Industry Matching

Industry is the primary semantic signal. Matching order is:

1. business industry;
2. section purpose and allowed position;
3. required visual type;
4. curated industry terms and relation bundles;
5. brand style and audience;
6. theme, responsive, accessibility, and quality gates.

The five requested profiles are explicitly modeled:

- Medical: doctor/clinic visuals, healthcare icons, trust/security motion.
- Manufacturing: factory objects, industry motion, engineering and technology icons.
- Restaurant: food objects, menu visuals, restaurant/lifestyle assets.
- Personal Brand: profile/resume visuals, communication icons, professional motion.
- Ecommerce: product objects, shopping animations, payment/delivery icons.

Company, education, construction, tourism, finance, and technology are also covered so the contract aligns with the existing Business Profile vocabulary. Exact terms and section preferences are maintained in `contracts/industry-placement-rules.json`.

Industry matching never overrides section incompatibility, ownership, status, quality, published-version, or licensing gates.
