---
paths:
  - 'app/Libraries/*.php'
---

# Libraries

## app/Libraries wraps 3rd-party classes for document generation
Use app/Libraries for classes that extend a third-party base class (e.g. FPDF) to produce documents/reports. Instantiate them directly with new in the calling controller rather than via DI. Keep pure formatting helpers in app/Helpers instead.
