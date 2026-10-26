# Autorización de deploy — fix CSP Google Ads ccTLD (2026-10-26)

Rama `fix/csp-google-ads-cctld-2026-10-26`, commits d063392 + e59fc63 (base baaa98e, en prod).

Anyerson, en la conversación con el jefe, tras preguntársele si desplegar el fix de CSP sin CRO ni cliente (cambio solo de header, sin efecto visible):

> "subelo y validamos"

Transcrito literal por el jefe (hilo principal). Gates presentes en disco:
- QA: docs/qa-csp-google-cctld.md — APROBADO (d063392) y APROBADO (e59fc63)
- Seguridad: docs/seguridad-csp-google-cctld.md — APTO (d063392); e59fc63 aplica sus dos hallazgos bajos
- CRO / cliente: no corridos, por autorización anterior (cambio de header sin superficie visual)
- Origen del defecto: docs/humo-prod-quitar-banner-2026-10-26.md
