# Google reCAPTCHA Enterprise plugin for Joomla! 6.0, 5.0, and 4.0.


## Plugin features
- Configurable rejection score threshold
- Option to show alternative captcha on failure
- Based on Joomla's Captcha API, supports compliant 3rd party extensions
- Uses reCAPTCHA Enterprise assessment API for server-side verification
- You can enable logging, which logs ip, name, email, scores and error messages. 
  - Focus is on the registration form, not clear how well the logging will work for other form types
  - The database logging automatically deletes log entries after 45 days (although this can be configured) to reduce data protection concerns
  - For small sites trying to stop spammers I think this is essential to log
  - Your privacy policy should already mention IP logging for security

## Setup Google ReCaptcha
- Create a GCP account, or login to [Google Cloud Console](https://console.cloud.google.com/) 
- Create a project if you haven't already
- Navigate to [reCapthca in the Security](https://console.cloud.google.com/security/recaptcha/) section
- Create reCAPTCHA Enterprise keys (scores based) to get your site key
- Navigate to [Credentials in the API section](https://console.cloud.google.com/apis/credentials) to create an API key
  - Restrict it to the reCAPTCHA Enterprise API


## Enable Captcha on your site
- Install this extension
- Using the information from the GCP console set:
  - **Site Key**
  - **API Key**
  - **Project ID**
- Enable database logging if you want to
- Enable the extenstion
- Goto System → Global Configuration → Site → Default Captcha
  - Select "reCAPTCHA Enterprise"
- Goto Users → Manage → Options
  - Select "Use global default" or "reCAPTCHA Enterprise"
- Test it on a registration form on your site!


## System Requirements
- Joomla! 4.0 or higher
- PHP 7.2.5 or higher

## Troubleshooting
- Since 1.3.0, CAPTCHA challenge is triggered on form focus by default. The challenge is automatically refreshed every 2 minutes. This should be compatible with most extensions. If, for some reason, this doesn't work and you're getting `Captcha answer is missing` errors:
  - tthe behavior can be changed using `Trigger Method` setting. `On Page Load` option is the most compatible with extensions but also triggers the challenge on every page load where CAPTCHA is displayed. 
  - This is not recommended for performance reasons and may exhaust your reCAPTCHA tokens. 
  - The challenge is also refreshed every 2 minutes. `On Form Submit` option triggers the challenge only when user submits the form. This is the most conservative option but is also incompatible with some extensions.
- The client side initField() function is used to load the token / display the captcha logo only when needed. This is important when the a form is configured on inital page load, but might never be called as you don't want the Captcha logo to appear all the time. This has *not* been fully tested in all scenarios
