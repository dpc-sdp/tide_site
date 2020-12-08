# Tide Site
Multi-site and multi-sections functionality for [Tide](https://github.com/dpc-sdp/tide) distribution for [Drupal 8](https://github.com/dpc-sdp)

Tide is a Drupal 8 distribution focused on delivering an API first, headless Drupal content administration site.

[![CircleCI](https://circleci.com/gh/dpc-sdp/tide_site.svg?style=shield&circle-token=2a0e49166724ac193636fba5b458024e00342dce)](https://circleci.com/gh/dpc-sdp/tide_site)
[![Release](https://img.shields.io/github/release/dpc-sdp/tide_site.svg)](https://github.com/dpc-sdp/tide_site/releases/latest)
![https://www.drupal.org/8](https://img.shields.io/badge/Drupal-8-blue.svg)
[![Licence: GPL 2](https://img.shields.io/badge/licence-GPL2-blue.svg)](https://github.com/dpc-sdp/tide_site/blob/master/LICENSE.txt)
[![Pull Requests](https://img.shields.io/github/issues-pr/dpc-sdp/tide_page.svg)](https://github.com/dpc-sdp/tide_site/pulls)

## What is in this package
- Fields for `Site` taxonomy vocabulary
- JSONAPI and Tide API module integration
- Preview Links support (with Tide API)
- Simple Sitemap integration configuration

## Installation
To install this package, add this custom repository to `repositories` section of
your `composer.json`:

```json
{
  "repositories": {        
      "dpc-sdp/tide_site": {
          "type": "vcs",
          "no-api": true,
          "url": "https://github.com/dpc-sdp/tide_site.git"
      }
  }
}
```

Require this package as any other Composer package:
```bash
composer require dpc/tide_site 
``` 

## Support
[Digital Engagement, Department of Premier and Cabinet, Victoria, Australia](https://github.com/dpc-sdp) 
is a maintainer of this package.

## Contribute
[Open an issue](https://github.com/dpc-sdp) on GitHub or submit a pull request with suggested changes.

## Development and maintenance
Development is powered by [Dev-Tools](https://github.com/dpc-sdp/dev-tools). Please refer to Dev-Tools' 
page for [system requirements](https://github.com/dpc-sdp/dev-tools/#prerequisites) and other details.

To start local development stack:
1. Checkout this project 
2. Run `./dev-tools.sh`
3. Run `ahoy build`
 
## Related projects
- [tide](https://github.com/dpc-sdp/tide)       
- [tide_api](https://github.com/dpc-sdp/tide_api)         
- [tide_core](https://github.com/dpc-sdp/tide_core)
- [tide_event](https://github.com/dpc-sdp/tide_event)
- [tide_landing_page](https://github.com/dpc-sdp/tide_landing_page)
- [tide_media](https://github.com/dpc-sdp/tide_media)     
- [tide_monsido](https://github.com/dpc-sdp/tide_monsido) 
- [tide_news](https://github.com/dpc-sdp/tide_news)       
- [tide_page](https://github.com/dpc-sdp/tide_page)       
- [tide_search](https://github.com/dpc-sdp/tide_search)     
- [tide_test](https://github.com/dpc-sdp/tide_test)       
- [tide_webform](https://github.com/dpc-sdp/tide_webform)  

## License
This project is licensed under [GPL2](https://github.com/dpc-sdp/tide_site/blob/master/LICENSE.txt)

## DRUSH
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

## Attribution
Single Digital Presence offers government agencies an open and flexible toolkit to build websites quickly and cost-effectively.
<p align="center"><a href="https://www.vic.gov.au/what-single-digital-presence-offers" target="_blank"><img src="docs/SDP_Logo_VicGov_RGB.jpg" alt="SDP logo" height="150"></a></p>

The Department of Premier and Cabinet partnered with Salsa Digital to deliver Single Digital Presence. As long-term supporters of open government approaches, they were integral to the establishment of SDP as an open source platform.
<p align="center"><a href="https://salsadigital.com.au/" target="_blank"><img src="docs/Salsa.png" alt="Salsa logo" height="150"></a></p>

