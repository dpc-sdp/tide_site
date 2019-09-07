# tide_site
Post content to multiple sites and sections.

[![CircleCI](https://circleci.com/gh/dpc-sdp/tide_site.svg?style=svg&circle-token=ee83834a70ce9ebad6fe586bbe0f365dfcc8d4e1)](https://circleci.com/gh/dpc-sdp/tide_site)

# CONTENTS OF THIS FILE

* Introduction
* Requirements
* Recommended Modules
* Installation
* Drush

# INTRODUCTION
The Tide Site module provides the functionality to post to multiple sites and sections from
 a single content server.

# REQUIREMENTS
* [Tide Core](https://github.com/dpc-sdp/tide_core)

# RECOMMENDED MODULES
* [Tide API](https://github.com/dpc-sdp/tide_api)
* [Tide Media](https://github.com/dpc-sdp/tide_media)

# INSTALLATION
Include the Tide Site module in your composer.json file

```bash
composer require dpc-sdp/tide_site
```

# DRUSH
The Drush command `tide-site-env-domain-update`, alias `tide-si-domup`, will
update one or more taxonomy terms in the Sites vocabulary with new Domains.
This command expects an environment variable `FE_DOMAINS` to exist. The var
must be in this format:
```
FE_DOMAINS="4|develop.premier.vic.gov.au,174|temp.exmaple.com<br/>example.com,172|dddtemp.exmaple.com<br/>dsa.example.com"
```
The var will get split into an array based on commas, each value being an
array of tids and domain values. This will then be split into an array based
on pipes. The key being the tid and value being a list of domains separated by
<br>, which will be converted into new lines. 

This command can be used to ensure the preview and url enhancer features will
work on headless sites on non production environments.
