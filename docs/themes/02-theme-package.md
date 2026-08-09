# RivaTheme Package

The official package extension is `.rivtheme`.

Required root file:

```json
{
  "schemaVersion": 1,
  "id": "aurora-test",
  "name": "Aurora Test",
  "version": "1.0.0",
  "engine": "^2.0",
  "apiVersion": "2026-08",
  "rivaLang": "1.0",
  "author": {
    "name": "Local Developer",
    "namespace": "local-store"
  },
  "templates": ["home"],
  "permissions": {
    "network": false,
    "arbitraryJavaScript": false,
    "serverExecution": false
  },
  "sections": []
}
```

Allowed source files are declarative theme files, schemas, locales, docs, and assets. Executable files are rejected.
