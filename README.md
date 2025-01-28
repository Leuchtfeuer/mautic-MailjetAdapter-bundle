Mailjet Adapter by Leuchtfeuer
==============================


### Risk of failure for API sending, for details see https://github.com/Leuchtfeuer/mautic-MailjetAdapter-bundle/issues/13


CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Configuration
* Author

INTRODUCTION
------------

This plugin enable Mautic 5 to run Mailjet as a email transport.

REQUIREMENTS
------------
- Mautic 5.x (minimum 5.1)
- PHP 8.0 or higher

INSTALLATION
------------

1. Get the plugin using `composer require leuchtfeuer/mautic-mailjetadapter-bundle`
2. Install it using `php bin\console mautic:plugins:reload`.
3. The plugin will start listing on plugin page.


CONFIGURATION
-------------

### Mautic 

This plugin provide two transports,
1. SMTP Relay:
   The best and fastest way to use the SMTP Relay is to have your own local mail server relaying messages to the Mailjet SMTP.
2. Email API:
   The Mailjet API is organized around REST. For more visit [Send API v3.1][SendApiV31Home].

|            | DSN (Data Source Name)                                                      | 
|------------|-----------------------------------------------------------------------------|
| SMTP Relay | `'mailer_dsn' => 'mautic+mailjet+smtp://<apiKey>:<secretKey>@default:465',` |
| Email API  | `'mailer_dsn' => 'mautic+mailjet+api://<apiKey>:<secretKey>@default:465',`  |


Follow the steps to set up Mailjet DSN,
1. Navigate to Configuration (/s/config/edit>)
2. Scroll to Email Settings
3. Update the following fields leaving rest default or empty,

| Field    | Value                                         |
|----------|-----------------------------------------------|
| Scheme   | `mautic+mailjet+smtp` or `mautic+mailjet+api` |
| Host     | `default`                                     |
| Port     | `465`                                         |
| User     | `<apiKey>`                                    |
| Password | `<secretKey>`                                 |

The `<apiKey>` and `<secretKey>` will be used for authentication purposes. Please visit the [Mailjet Guide][MailjetGuidePage]

On the Configuration page **Email DSN** should look like ![Email DSN](Docs/imgs/02%20-%20Email%20DSN.png "Email DSN")

### Mailjet

1. Create a [Mailjet account][MailjetSignup]
2. Then [retrieve your API and Secret keys][RetrieveKeys]. They will be used for authentication purposes.
3. Set up the webhook hook for event tracking from your account preferences, in the [Event Tracking section][EventTrackingSection]. The webhook URL should be `https://<your-domain.tld>/mailer/callback`.

 


AUTHOR AND CONTACT
------

Leuchtfeuer Digital Marketing GmbH

Please raise any issues in GitHub.

For all other things, please email mautic-plugins@Leuchtfeuer.com


[MailjetGuidePage]: <https://dev.mailjet.com/email/guides/getting-started/>
[SendApiV31Home]: <https://dev.mailjet.com/email/guides/send-api-v31/>
[MailjetSignup]: <https://app.mailjet.com/signup>
[RetrieveKeys]: <https://app.mailjet.com/account/api_keys>
[EventTrackingSection]: <https://app.mailjet.com/account/triggers>
