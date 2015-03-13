# mediawiki-gsa-interwiki
Automatically exported from code.google.com/p/mediawiki-gsa-interwiki

This project is an enhancement of the mediawiki-gsa-mediawiki project. It fixes a couple issues experienced with that plugin and adds support for searching multiple wikis. This is setup specific: In our case, we have multiple wikis installed and an example of this would be a "knowledge" wiki and a "products" wiki like so:

http://server/wikis_or_some_path/knowledge
http://server/wikis_or_some_path/products
The need was to keep these wikis separate yet include results from the other wiki(s) when searching a particular wiki. This was added by returning results for the other wiki(s) as interwiki results which are displayed in a box to the right of "Page text matches". No changes to the core Mediawiki installation are required besides adding the extension and configuration (LocalSettings?.php and two special Mediawiki: pages).
