# Theme Security

Uploaded themes are untrusted until validation passes.

Current enforced checks:

- `.rivtheme` extension
- package size limit
- file count limit
- uncompressed size limit
- safe relative paths
- forbidden executable extensions
- asset/source extension whitelist
- required manifest fields
- RivaTheme Engine 2.x compatibility
- no network, arbitrary JavaScript, or server execution permissions
- RivaLang component/filter/safe object validation

Valid packages are installed as draft and never become live automatically.
