---
paths:
  - 'routes/*.php'
---

# Routes

## Assign middleware in route files, not controllers
Attach middleware via ->middleware() on routes/route groups. Do not implement HasMiddleware, #[Middleware] attributes, or $this->middleware() inside controllers.
