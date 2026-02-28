# Google reCAPTCHA Enterprise plugin for Joomla! 6.0, 5.0, and 4.0.
Register on the [Google Cloud Console](https://console.cloud.google.com/) and create reCAPTCHA Enterprise keys to get your site key, API key, and project ID.
## Plugin features
- Configurable rejection score threshold
- Option to show alternative captcha on failure
- Based on Joomla's Captcha API, supports compliant 3rd party extensions
- Uses reCAPTCHA Enterprise assessment API for server-side verification

## Enable Captha on your site
- Install this extension
- Goto System → Global Configuration → Site → Default Captcha
  - Select "reCAPTCHA v3"
- Goto Users → Manage → Options
  - Select "Use global default" or "reCAPTCHA v3"

## Setup and configure plugin options
1. Create a Google Cloud project and enable the reCAPTCHA Enterprise API.
2. Create a reCAPTCHA Enterprise site key (score-based / v3 type) in the Cloud Console.
3. Create an API key in the Cloud Console (restrict it to the reCAPTCHA Enterprise API).
4. In the Joomla plugin settings, enter: **Site Key**, **API Key**, and **Project ID**.

## System Requirements
- Joomla! 4.0 or higher
- PHP 7.2.5 or higher

## Troubleshooting
Since 1.3.0, CAPTCHA challenge is triggered on form focus by default. The challenge is automatically refreshed every 2 minutes. This should be compatible with most extensions. If, for some reason, this doesn't work and you're getting `Captcha answer is missing` errors, the behavior can be changed using `Trigger Method` setting. `On Page Load` option is the most compatible with extensions but also triggers the challenge on every page load where CAPTCHA is displayed. This is not recommended for performance reasons and may exhaust your reCAPTCHA tokens. The challenge is also refreshed every 2 minutes. `On Form Submit` option triggers the challenge only when user submits the form. This is the most conservative option but is also incompatible with some extensions.
