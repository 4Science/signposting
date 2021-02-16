# Signposting plugin

**NOTE: Please ensure you're using the correct branch. Use the [master branch](https://github.com/asmecher/signposting/tree/master) for OJS 3.x, and the [ojs-dev-2_4 branch](https://github.com/asmecher/signposting/tree/ojs-dev-2_4) for OJS 2.4.x.**

Plugin to enable the Signposting feature (tested with OJS 3.x)

Copyright © 2015-2016 University of Pittsburgh
<br />Copyright © 2014-2017 Simon Fraser University Library
<br />Copyright © 2003-2017 John Willinsky

Licensed under GPL 2 or better.

Contributed by 4Science (https://www.4science.it).

## Features:

Implements the following Signposting ([http://signposting.org](http://signposting.org)) patterns:
 * Author (with [ORCID](https://orcid.org));
 * Bibliographic metadata;
 * Identifier;
 * Publication boundary.

## Requirements:
To be able to use the Author pattern the following plugin is required:
 * ORCID Profile: downloadable at [https://github.com/pkp/orcidProfile](https://github.com/pkp/orcidProfile)
To be able to use the Bibliographic Metadata Pattern the following plugin is required:
 * Citation Style Language: downloadable at [https://github.com/pkp/citationStyleLanguage](https://github.com/pkp/citationStyleLanguage)
 
## Install:

 * Copy the source into the PKP product's plugins/generic folder.
 * Run `tools/upgrade.php upgrade` to allow the system to recognize the new plugin.
 * Enable this plugin within the administration interface.
