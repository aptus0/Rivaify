# RivaLang

RivaLang is a sandboxed, side-effect-free commerce template DSL.

It supports safe output expressions:

```riva
{{ product.title }}
{{ product.price | money }}
{{ section.settings.heading | escape }}
```

It supports controlled statements:

```riva
{% if product.available %}
  <riva-badge>Stokta</riva-badge>
{% else %}
  <riva-badge scheme="danger">Tukendi</riva-badge>
{% endif %}
```

Themes cannot access files, sockets, HTTP, environment variables, database queries, PHP, Node, or browser globals. The compiler emits diagnostics with file, line, and column locations.
