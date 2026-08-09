# Rivaify Theme Platform

Rivaify themes are installed as `.rivtheme` packages. A package is treated as untrusted input until it passes quarantine validation, manifest checks, RivaLang compilation, asset scanning, and compatibility reporting.

Valid themes install as draft only. Publishing is a separate merchant action from Visual Commerce Studio.

## Package Flow

1. Upload `.rivtheme`
2. Store in quarantine
3. Validate `riva.theme.json`
4. Compile `.riva` templates into safe IR
5. Scan assets and forbidden files
6. Store compiled artifact
7. Install as draft store theme
8. Edit in Visual Commerce Studio
9. Publish when ready

Theme packages never run PHP, Node, shell scripts, arbitrary JavaScript, Composer, or NPM install steps.
