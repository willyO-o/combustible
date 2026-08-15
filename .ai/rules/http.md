---
paths:
  - 'app/Http/**/*.php'
---

# Http

## Use route() for all redirects/URL generation
Generate redirect targets and URLs with route('name', ...). Never hardcode paths with url() or use action([...]).
